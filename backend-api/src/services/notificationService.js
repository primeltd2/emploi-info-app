import crypto from "node:crypto";
import { env } from "../config/env.js";
import { dataPaths } from "../config/paths.js";
import { readJson, writeJson } from "../repositories/jsonRepository.js";

let cachedAccessToken = null;

function normalizeArray(value) {
  return Array.isArray(value) ? value : [];
}

function base64Url(input) {
  return Buffer.from(input).toString("base64").replace(/\+/g, "-").replace(/\//g, "_").replace(/=+$/g, "");
}

function firebaseServiceAccount() {
  if (env.FIREBASE_SERVICE_ACCOUNT_JSON) {
    try {
      const parsed = JSON.parse(env.FIREBASE_SERVICE_ACCOUNT_JSON);
      if (parsed.project_id && parsed.client_email && parsed.private_key) return parsed;
    } catch {
      return null;
    }
  }

  if (env.FIREBASE_PROJECT_ID && env.FIREBASE_CLIENT_EMAIL && env.FIREBASE_PRIVATE_KEY) {
    return {
      project_id: env.FIREBASE_PROJECT_ID,
      client_email: env.FIREBASE_CLIENT_EMAIL,
      private_key: env.FIREBASE_PRIVATE_KEY.replace(/\\n/g, "\n")
    };
  }

  return null;
}

async function firebaseAccessToken() {
  const now = Math.floor(Date.now() / 1000);
  if (cachedAccessToken && cachedAccessToken.expiresAt > now + 60) return cachedAccessToken.token;

  const account = firebaseServiceAccount();
  if (!account) return null;

  const header = base64Url(JSON.stringify({ alg: "RS256", typ: "JWT" }));
  const claims = base64Url(JSON.stringify({
    iss: account.client_email,
    scope: "https://www.googleapis.com/auth/firebase.messaging",
    aud: "https://oauth2.googleapis.com/token",
    iat: now,
    exp: now + 3600
  }));
  const unsigned = `${header}.${claims}`;
  const signature = crypto.createSign("RSA-SHA256").update(unsigned).sign(account.private_key);
  const jwt = `${unsigned}.${base64Url(signature)}`;

  const response = await fetch("https://oauth2.googleapis.com/token", {
    method: "POST",
    headers: { "Content-Type": "application/x-www-form-urlencoded" },
    body: new URLSearchParams({
      grant_type: "urn:ietf:params:oauth:grant-type:jwt-bearer",
      assertion: jwt
    })
  });
  const body = await response.json().catch(() => ({}));
  if (!response.ok || !body.access_token) return null;

  cachedAccessToken = {
    token: body.access_token,
    expiresAt: now + Number(body.expires_in || 3600)
  };
  return cachedAccessToken.token;
}

function notificationBody(offer) {
  const raw = String(offer?.notice || offer?.texte || "Une nouvelle offre est disponible.");
  return raw.replace(/<[^>]*>/g, " ").replace(/\s+/g, " ").trim().slice(0, 160);
}

function offerUrl(offer) {
  const id = encodeURIComponent(String(offer?.id || ""));
  return `https://app.local/details.html?id=${id}`;
}

async function sendFcmMessage(message) {
  const account = firebaseServiceAccount();
  const token = await firebaseAccessToken();
  if (!account || !token) return { success: false, code: 0, response: "Firebase non configure" };

  const response = await fetch(
    `https://fcm.googleapis.com/v1/projects/${encodeURIComponent(account.project_id)}/messages:send`,
    {
      method: "POST",
      headers: {
        Authorization: `Bearer ${token}`,
        "Content-Type": "application/json"
      },
      body: JSON.stringify({ message })
    }
  );
  const body = await response.json().catch(async () => response.text().catch(() => ""));
  return { success: response.ok, code: response.status, response: body };
}

export async function wasOfferNotificationSent(offerId) {
  if (!offerId) return true;
  const sent = await readJson(dataPaths.notificationSent, {});
  return Boolean(sent[offerId]?.sent);
}

async function markOfferNotificationSent(offerId, context = {}) {
  if (!offerId) return;
  const sent = await readJson(dataPaths.notificationSent, {});
  sent[offerId] = { sent: true, sent_at: new Date().toISOString(), ...context };
  await writeJson(dataPaths.notificationSent, sent);
}

export async function sendAndroidOfferNotification(offer, sendNumber = 1) {
  const tokens = normalizeArray(await readJson(dataPaths.androidTokens));
  const offerId = String(offer?.id || "");
  if (!tokens.length) return { status: "no_android_tokens", count: 0, report: [] };
  if (sendNumber <= 1 && await wasOfferNotificationSent(offerId)) {
    return { status: "already_sent_android", count: 0, report: [] };
  }

  const title = `${sendNumber > 1 ? "Rappel : " : ""}${String(offer?.titre || "Nouvelle offre")}`;
  const body = notificationBody(offer);
  const url = offerUrl(offer);
  const kept = [];
  const report = [];

  for (const row of tokens) {
    const token = String(row?.token || "");
    if (!token) continue;
    const result = await sendFcmMessage({
      token,
      data: {
        title,
        body,
        url,
        offer_id: offerId,
        item_type: "annonce",
        comment_url: "/interactions/comment",
        send_number: String(sendNumber),
        type: "annonce"
      },
      android: {
        priority: "HIGH",
        collapse_key: offerId ? `offer_${offerId}_${sendNumber}` : `emploi_info_offer_${sendNumber}`,
        direct_boot_ok: true
      }
    });
    report.push({ token: `${token.slice(0, 12)}...`, result });

    const responseText = JSON.stringify(result.response || "");
    const invalid = !result.success && (
      result.code === 404 ||
      responseText.includes("UNREGISTERED") ||
      responseText.includes("INVALID_ARGUMENT")
    );
    if (!invalid) kept.push(row);
  }

  await writeJson(dataPaths.androidTokens, kept);
  if (sendNumber <= 1) await markOfferNotificationSent(offerId, { android_count: report.length });
  return { status: "sent_android", count: report.length, report };
}

export async function queueOfferReminder(offer, delaySeconds) {
  const offerId = String(offer?.id || "");
  if (!offerId) return;
  const queue = normalizeArray(await readJson(dataPaths.notificationReminders));
  const nextQueue = queue.filter((row) => row.offer_id !== offerId || row.sent);
  nextQueue.push({
    offer_id: offerId,
    offer,
    send_after: Date.now() + delaySeconds * 1000,
    sent: false,
    created_at: new Date().toISOString()
  });
  await writeJson(dataPaths.notificationReminders, nextQueue);
}

export async function processDueOfferReminders() {
  const queue = normalizeArray(await readJson(dataPaths.notificationReminders));
  const now = Date.now();
  let changed = false;
  const sent = [];

  for (const row of queue) {
    if (row.sent || Number(row.send_after || 0) > now) continue;
    const result = await sendAndroidOfferNotification(row.offer, 2);
    row.sent = true;
    row.sent_at = new Date().toISOString();
    row.result = { status: result.status, count: result.count };
    sent.push(row.offer_id);
    changed = true;
  }

  if (changed) await writeJson(dataPaths.notificationReminders, queue);
  return sent;
}

export function startReminderWorker() {
  processDueOfferReminders().catch(() => {});
  setInterval(() => {
    processDueOfferReminders().catch(() => {});
  }, 5 * 60 * 1000).unref();
}
