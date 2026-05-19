import { query, withTransaction } from "../config/database.js";

export async function listOffersRows({ limit = 30, category, city, includeDrafts = false } = {}) {
  const where = [];
  const params = [];

  if (!includeDrafts) where.push("published = TRUE");
  if (category) {
    params.push(category.toLowerCase());
    where.push(`LOWER(category) = $${params.length}`);
  }
  if (city) {
    params.push(city.toLowerCase());
    where.push(`LOWER(city) = $${params.length}`);
  }

  params.push(limit);
  const { rows } = await query(
    `SELECT * FROM offers ${where.length ? `WHERE ${where.join(" AND ")}` : ""}
     ORDER BY published_at DESC NULLS LAST, created_at DESC
     LIMIT $${params.length}`,
    params
  );
  return rows;
}

export async function getOfferRow(id, { includeDrafts = false } = {}) {
  const { rows } = await query(
    `SELECT * FROM offers WHERE id = $1 ${includeDrafts ? "" : "AND published = TRUE"} LIMIT 1`,
    [id]
  );
  return rows[0] || null;
}

export async function createOfferRow(input) {
  const id = `ann_${Date.now().toString(16)}${Math.random().toString(16).slice(2, 7)}`;
  const now = new Date();
  const published = Boolean(input.published);
  const { rows } = await query(
    `INSERT INTO offers
      (id, title, description, notice, category, city, banner, buttons, alignment, urgent, published, published_at, created_at, updated_at)
     VALUES ($1,$2,$3,$4,$5,$6,$7,$8,$9,$10,$11,$12,$13,$13)
     RETURNING *`,
    [
      id,
      input.title || "",
      input.description || "",
      input.notice || "",
      input.category || "",
      input.city || "",
      input.banner || "",
      JSON.stringify(Array.isArray(input.buttons) ? input.buttons : []),
      input.alignment || "left",
      Boolean(input.urgent),
      published,
      published ? now : null,
      now
    ]
  );
  return rows[0];
}

export async function updateOfferRow(id, input) {
  return withTransaction(async (client) => {
    const current = await client.query("SELECT * FROM offers WHERE id = $1 FOR UPDATE", [id]);
    const existing = current.rows[0];
    if (!existing) return null;
    const nextPublished = input.published === undefined ? existing.published : Boolean(input.published);
    const publishedAt = !existing.published && nextPublished ? new Date() : existing.published_at;
    const { rows } = await client.query(
      `UPDATE offers SET
        title = $2,
        description = $3,
        notice = $4,
        category = $5,
        city = $6,
        banner = $7,
        buttons = $8,
        alignment = $9,
        urgent = $10,
        published = $11,
        published_at = $12,
        updated_at = NOW()
       WHERE id = $1
       RETURNING *`,
      [
        id,
        input.title ?? existing.title,
        input.description ?? existing.description,
        input.notice ?? existing.notice,
        input.category ?? existing.category,
        input.city ?? existing.city,
        input.banner ?? existing.banner,
        JSON.stringify(Array.isArray(input.buttons) ? input.buttons : existing.buttons || []),
        input.alignment ?? existing.alignment,
        input.urgent ?? existing.urgent,
        nextPublished,
        publishedAt
      ]
    );
    return rows[0];
  });
}

export async function deleteOfferRow(id) {
  const result = await query("DELETE FROM offers WHERE id = $1", [id]);
  return result.rowCount > 0;
}

export async function getCatalogRows() {
  const { rows } = await query("SELECT name, items FROM catalog_lists");
  return Object.fromEntries(rows.map((row) => [row.name, row.items || []]));
}

export async function replaceCatalogRows(name, items) {
  const { rows } = await query(
    `INSERT INTO catalog_lists (name, items, updated_at)
     VALUES ($1, $2, NOW())
     ON CONFLICT (name) DO UPDATE SET items = EXCLUDED.items, updated_at = NOW()
     RETURNING items`,
    [name, JSON.stringify(items)]
  );
  return rows[0]?.items || [];
}

export async function listContentRows(section) {
  const { rows } = await query(
    "SELECT id, payload, created_at FROM content_items WHERE section = $1 AND published = TRUE ORDER BY created_at DESC",
    [section]
  );
  return rows.map((row) => ({ id: row.id, date: row.created_at, ...row.payload }));
}

export async function createContentRow(section, input) {
  const id = `sec_${Date.now().toString(16)}${Math.random().toString(16).slice(2, 7)}`;
  const { rows } = await query(
    `INSERT INTO content_items (id, section, payload, published, created_at, updated_at)
     VALUES ($1,$2,$3,TRUE,NOW(),NOW())
     RETURNING id, payload, created_at`,
    [id, section, JSON.stringify(input)]
  );
  return { id: rows[0].id, date: rows[0].created_at, ...rows[0].payload };
}

export async function deleteContentRow(section, id) {
  const result = await query("DELETE FROM content_items WHERE section = $1 AND id = $2", [section, id]);
  return result.rowCount > 0;
}

export async function listAdsRows() {
  const { rows } = await query("SELECT id, payload, created_at FROM ads WHERE active = TRUE ORDER BY created_at DESC");
  return rows.map((row) => ({ id: row.id, date: row.created_at, ...row.payload }));
}

export async function upsertAdsRows(items) {
  await query("DELETE FROM ads");
  for (const item of items) {
    const id = String(item.id || `ad_${Date.now().toString(16)}${Math.random().toString(16).slice(2, 7)}`);
    await query(
      "INSERT INTO ads (id, payload, active, created_at, updated_at) VALUES ($1,$2,TRUE,NOW(),NOW()) ON CONFLICT (id) DO UPDATE SET payload = EXCLUDED.payload, active = TRUE, updated_at = NOW()",
      [id, JSON.stringify({ ...item, id })]
    );
  }
}

export async function getSettingRow(name, fallback = {}) {
  const { rows } = await query("SELECT value FROM app_settings WHERE name = $1", [name]);
  return rows[0]?.value ?? fallback;
}

export async function upsertSettingRow(name, value) {
  await query(
    `INSERT INTO app_settings (name, value, updated_at)
     VALUES ($1,$2,NOW())
     ON CONFLICT (name) DO UPDATE SET value = EXCLUDED.value, updated_at = NOW()`,
    [name, JSON.stringify(value)]
  );
}

export async function saveAndroidTokenRow(input) {
  await query(
    `INSERT INTO android_tokens (token, platform, app, created_at, updated_at)
     VALUES ($1,$2,$3,NOW(),NOW())
     ON CONFLICT (token) DO UPDATE SET platform = EXCLUDED.platform, app = EXCLUDED.app, updated_at = NOW()`,
    [String(input.token), input.platform || "android", input.app || "emploi-info"]
  );
  return { saved: true };
}

export async function listAndroidTokenRows() {
  const { rows } = await query("SELECT token, platform, app FROM android_tokens ORDER BY updated_at DESC");
  return rows;
}

export async function removeAndroidTokens(tokens) {
  if (!tokens.length) return;
  await query("DELETE FROM android_tokens WHERE token = ANY($1::text[])", [tokens]);
}

export async function wasNotificationSentRow(offerId) {
  const { rowCount } = await query("SELECT 1 FROM notification_sent WHERE offer_id = $1", [offerId]);
  return rowCount > 0;
}

export async function markNotificationSentRow(offerId, context = {}) {
  await query(
    `INSERT INTO notification_sent (offer_id, context, sent_at)
     VALUES ($1,$2,NOW())
     ON CONFLICT (offer_id) DO UPDATE SET context = EXCLUDED.context, sent_at = NOW()`,
    [offerId, JSON.stringify(context)]
  );
}

export async function queueReminderRow(offer, delaySeconds) {
  await query(
    `INSERT INTO notification_reminders (offer_id, offer, send_after, sent, created_at)
     VALUES ($1,$2,NOW() + ($3 || ' seconds')::interval,FALSE,NOW())
     ON CONFLICT (offer_id) DO UPDATE SET offer = EXCLUDED.offer, send_after = EXCLUDED.send_after, sent = FALSE`,
    [String(offer.id), JSON.stringify(offer), Number(delaySeconds)]
  );
}

export async function dueReminderRows() {
  const { rows } = await query(
    "SELECT id, offer_id, offer FROM notification_reminders WHERE sent = FALSE AND send_after <= NOW() ORDER BY send_after ASC LIMIT 100"
  );
  return rows;
}

export async function markReminderSent(id, result) {
  await query(
    "UPDATE notification_reminders SET sent = TRUE, sent_at = NOW(), result = $2 WHERE id = $1",
    [id, JSON.stringify(result)]
  );
}
