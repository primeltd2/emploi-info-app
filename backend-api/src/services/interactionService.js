import crypto from "node:crypto";
import { dataPaths } from "../config/paths.js";
import { readJson, writeJson } from "../repositories/jsonRepository.js";

function cleanText(value, max = 1200) {
  return String(value || "")
    .replace(/<[^>]*>/g, "")
    .replace(/\s+/g, " ")
    .trim()
    .slice(0, max);
}

export async function addComment(input) {
  const itemType = cleanText(input.item_type || "annonce", 40).replace(/[^a-z0-9_-]/gi, "") || "annonce";
  const itemId = cleanText(input.item_id, 120).replace(/[^a-z0-9_-]/gi, "");
  const text = cleanText(input.text);
  const visitorId = cleanText(input.visitor_id || `android-${crypto.randomUUID()}`, 180);
  const username = cleanText(input.username || "Utilisateur Android", 40);

  if (!itemId || !text) {
    const error = new Error("Parametres manquants");
    error.status = 400;
    throw error;
  }

  const data = await readJson(dataPaths.interactions, {});
  const key = `${itemType}:${itemId}`;
  const item = data[key] || { reactions: [], comments: [] };
  item.comments = Array.isArray(item.comments) ? item.comments : [];

  const comment = {
    id: `com_${Date.now().toString(16)}${Math.random().toString(16).slice(2, 7)}`,
    parent_id: "",
    reply_to: "",
    username,
    visitor_hash: crypto.createHash("sha256").update(visitorId).digest("hex"),
    ip_hash: "",
    text,
    status: "approved",
    reason: "",
    date: new Date().toISOString(),
    source: "android_notification"
  };

  item.comments.push(comment);
  data[key] = item;
  await writeJson(dataPaths.interactions, data);

  return {
    status: "success",
    message: "Commentaire envoye.",
    comment: {
      id: comment.id,
      username: comment.username,
      text: comment.text,
      status: comment.status,
      date: comment.date
    }
  };
}
