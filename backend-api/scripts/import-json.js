import fs from "node:fs/promises";
import path from "node:path";
import { env } from "../src/config/env.js";
import { query, pool } from "../src/config/database.js";

function asArray(value) {
  return Array.isArray(value) ? value : [];
}

async function readJson(name, fallback = []) {
  const file = path.join(env.SEED_DATA_DIR_ABSOLUTE, name);
  const raw = await fs.readFile(file, "utf8").catch(() => "");
  return raw.trim() ? JSON.parse(raw) : fallback;
}

function isPublished(row) {
  return row?.publie === true || row?.publie === "true" || row?.publie === 1;
}

await query(await fs.readFile(new URL("../db/schema.sql", import.meta.url), "utf8"));

const offers = asArray(await readJson("data.json"));
for (const row of offers) {
  const published = isPublished(row);
  const createdAt = row.date ? new Date(row.date) : new Date();
  await query(
    `INSERT INTO offers
      (id, title, description, notice, category, city, banner, buttons, alignment, urgent, published, published_at, created_at, updated_at)
     VALUES ($1,$2,$3,$4,$5,$6,$7,$8,$9,$10,$11,$12,$13,$13)
     ON CONFLICT (id) DO UPDATE SET
      title = EXCLUDED.title,
      description = EXCLUDED.description,
      notice = EXCLUDED.notice,
      category = EXCLUDED.category,
      city = EXCLUDED.city,
      banner = EXCLUDED.banner,
      buttons = EXCLUDED.buttons,
      alignment = EXCLUDED.alignment,
      urgent = EXCLUDED.urgent,
      published = EXCLUDED.published,
      published_at = EXCLUDED.published_at,
      updated_at = NOW()`,
    [
      String(row.id || `ann_${Date.now().toString(16)}${Math.random().toString(16).slice(2, 7)}`),
      row.titre || "",
      row.texte || "",
      row.notice || "",
      row.categorie || "",
      row.ville || "",
      row.banniere || "",
      JSON.stringify(Array.isArray(row.boutons) ? row.boutons : []),
      row.alignement || "left",
      Boolean(row.urgent),
      published,
      published ? createdAt : null,
      createdAt
    ]
  );
}

for (const [name, file] of Object.entries({
  categories: "categories.json",
  cities: "villes.json",
  resources: "resources.json",
  services: "services.json",
  blog: "blog.json",
  formations: "formations.json"
})) {
  const items = asArray(await readJson(file));
  await query(
    `INSERT INTO catalog_lists (name, items, updated_at)
     VALUES ($1,$2,NOW())
     ON CONFLICT (name) DO UPDATE SET items = EXCLUDED.items, updated_at = NOW()`,
    [name, JSON.stringify(items)]
  );
}

for (const [section, file] of Object.entries({
  resources: "resources.json",
  services: "services.json",
  blog: "blog.json",
  formations: "formations.json"
})) {
  const items = asArray(await readJson(file));
  for (const item of items) {
    const id = String(item.id || `sec_${Date.now().toString(16)}${Math.random().toString(16).slice(2, 7)}`);
    await query(
      `INSERT INTO content_items (id, section, payload, published, created_at, updated_at)
       VALUES ($1,$2,$3,TRUE,$4,$4)
       ON CONFLICT (id) DO UPDATE SET section = EXCLUDED.section, payload = EXCLUDED.payload, updated_at = NOW()`,
      [id, section, JSON.stringify({ ...item, id }), item.date ? new Date(item.date) : new Date()]
    );
  }
}

const ads = asArray(await readJson("ads.json"));
for (const item of ads) {
  const id = String(item.id || `ad_${Date.now().toString(16)}${Math.random().toString(16).slice(2, 7)}`);
  await query(
    `INSERT INTO ads (id, payload, active, created_at, updated_at)
     VALUES ($1,$2,TRUE,NOW(),NOW())
     ON CONFLICT (id) DO UPDATE SET payload = EXCLUDED.payload, active = TRUE, updated_at = NOW()`,
    [id, JSON.stringify({ ...item, id })]
  );
}

for (const [name, file] of Object.entries({
  params: "params.json",
  stats: "stats.json",
  app_version: "app_version.json",
  admin_app_version: "admin_app_version.json"
})) {
  const value = await readJson(file, {});
  await query(
    `INSERT INTO app_settings (name, value, updated_at)
     VALUES ($1,$2,NOW())
     ON CONFLICT (name) DO UPDATE SET value = EXCLUDED.value, updated_at = NOW()`,
    [name, JSON.stringify(value)]
  );
}

await pool.end();
console.log(`Imported ${offers.length} offers, ${ads.length} ads, content sections, catalog lists and settings from JSON seed files.`);
