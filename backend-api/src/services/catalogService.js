import { dataPaths } from "../config/paths.js";
import { readJson, writeJson } from "../repositories/jsonRepository.js";
import {
  queueOfferReminder,
  sendAndroidOfferNotification,
  wasOfferNotificationSent
} from "./notificationService.js";

function normalizeArray(value) {
  return Array.isArray(value) ? value : [];
}

function visibleOffer(offer) {
  return offer && (offer.publie === true || offer.publie === "true" || offer.publie === 1);
}

export async function listOffers({ limit = 30, category, city, includeDrafts = false } = {}) {
  const rows = normalizeArray(await readJson(dataPaths.offers));
  let offers = includeDrafts ? rows : rows.filter(visibleOffer);

  if (category) {
    const needle = category.toLowerCase();
    offers = offers.filter((offer) => String(offer.categorie || "").toLowerCase() === needle);
  }

  if (city) {
    const needle = city.toLowerCase();
    offers = offers.filter((offer) => String(offer.ville || "").toLowerCase() === needle);
  }

  return offers.slice(0, limit).map(toPublicOffer);
}

export async function getOffer(id, { includeDrafts = false } = {}) {
  const rows = normalizeArray(await readJson(dataPaths.offers));
  const offer = rows.find((row) => String(row.id) === String(id));
  if (!offer || (!includeDrafts && !visibleOffer(offer))) return null;
  return toPublicOffer(offer);
}

export async function listAdminOffers({ limit = 100 } = {}) {
  const rows = normalizeArray(await readJson(dataPaths.offers));
  return rows.slice(0, limit).map(toPublicOffer);
}

export async function createOffer(input) {
  const rows = normalizeArray(await readJson(dataPaths.offers));
  const offer = fromApiOffer({
    ...input,
    id: `ann_${Date.now().toString(16)}${Math.random().toString(16).slice(2, 7)}`,
    published: input.published ?? false,
    date: new Date().toISOString()
  });

  rows.unshift(offer);
  await writeJson(dataPaths.offers, rows);
  if (visibleOffer(offer)) await notifyPublishedOffer(offer);
  return toPublicOffer(offer);
}

export async function updateOffer(id, input) {
  const rows = normalizeArray(await readJson(dataPaths.offers));
  const index = rows.findIndex((row) => String(row.id) === String(id));
  if (index < 0) return null;

  rows[index] = {
    ...rows[index],
    ...fromApiOffer({ ...toPublicOffer(rows[index]), ...input, id: rows[index].id }, rows[index])
  };
  await writeJson(dataPaths.offers, rows);
  return toPublicOffer(rows[index]);
}

export async function setOfferPublished(id, published) {
  const beforeRows = normalizeArray(await readJson(dataPaths.offers));
  const before = beforeRows.find((row) => String(row.id) === String(id));
  const offer = await updateOffer(id, { published });
  if (offer && published && !visibleOffer(before)) {
    const rows = normalizeArray(await readJson(dataPaths.offers));
    const rawOffer = rows.find((row) => String(row.id) === String(id));
    if (rawOffer) await notifyPublishedOffer(rawOffer);
  }
  return offer;
}

export async function deleteOffer(id) {
  const rows = normalizeArray(await readJson(dataPaths.offers));
  const nextRows = rows.filter((row) => String(row.id) !== String(id));
  if (nextRows.length === rows.length) return false;

  await writeJson(dataPaths.offers, nextRows);
  return true;
}

export async function getCatalog() {
  const [categories, cities, resources, services, blog, formations] = await Promise.all([
    readJson(dataPaths.categories),
    readJson(dataPaths.cities),
    readJson(dataPaths.resources),
    readJson(dataPaths.services),
    readJson(dataPaths.blog),
    readJson(dataPaths.formations)
  ]);

  return {
    categories: normalizeArray(categories),
    cities: normalizeArray(cities),
    resources: normalizeArray(resources),
    services: normalizeArray(services),
    blog: normalizeArray(blog),
    formations: normalizeArray(formations)
  };
}

export async function replaceCatalogList(name, items) {
  const allowed = {
    categories: dataPaths.categories,
    cities: dataPaths.cities
  };
  const filePath = allowed[name];
  if (!filePath) return null;

  const normalized = [...new Set(
    normalizeArray(items)
      .map((item) => String(item).trim())
      .filter(Boolean)
  )];
  await writeJson(filePath, normalized);
  return normalized;
}

export async function getAds() {
  return normalizeArray(await readJson(dataPaths.ads));
}

export async function getAppVersion(kind = "public") {
  const file = kind === "admin" ? dataPaths.adminAppVersion : dataPaths.appVersion;
  return readJson(file, {});
}

export async function saveAndroidToken(input) {
  const rows = normalizeArray(await readJson(dataPaths.androidTokens));
  const now = new Date().toISOString();
  const token = String(input.token);
  const index = rows.findIndex((row) => row && row.token === token);
  const record = {
    token,
    platform: input.platform || "android",
    app: input.app || "emploi-info",
    updated_at: now
  };

  if (index >= 0) {
    rows[index] = { ...rows[index], ...record };
  } else {
    rows.unshift({ ...record, created_at: now });
  }

  await writeJson(dataPaths.androidTokens, rows);
  return { saved: true };
}

export async function listContentSection(section) {
  const filePath = sectionFilePath(section);
  if (!filePath) return null;
  return normalizeArray(await readJson(filePath));
}

export async function createContentSectionItem(section, input) {
  const filePath = sectionFilePath(section);
  if (!filePath) return null;
  const rows = normalizeArray(await readJson(filePath));
  const item = {
    ...input,
    id: `sec_${Date.now().toString(16)}${Math.random().toString(16).slice(2, 7)}`,
    date: new Date().toISOString()
  };
  rows.unshift(item);
  await writeJson(filePath, rows);
  return item;
}

export async function deleteContentSectionItem(section, id) {
  const filePath = sectionFilePath(section);
  if (!filePath) return null;
  const rows = normalizeArray(await readJson(filePath));
  const nextRows = rows.filter((row) => String(row.id) !== String(id));
  if (nextRows.length === rows.length) return false;
  await writeJson(filePath, nextRows);
  return true;
}

function sectionFilePath(section) {
  const allowed = {
    blog: dataPaths.blog,
    resources: dataPaths.resources,
    services: dataPaths.services,
    formations: dataPaths.formations
  };
  return allowed[section] || null;
}

async function notifyPublishedOffer(offer) {
  const id = String(offer?.id || "");
  if (!id || await wasOfferNotificationSent(id)) return;
  await sendAndroidOfferNotification(offer, 1);
  await queueOfferReminder(offer, 4 * 60 * 60);
}

function toPublicOffer(offer) {
  return {
    id: offer.id,
    title: offer.titre || "",
    description: offer.texte || "",
    notice: offer.notice || "",
    category: offer.categorie || "",
    city: offer.ville || "",
    banner: offer.banniere || "",
    buttons: Array.isArray(offer.boutons) ? offer.boutons : [],
    alignment: offer.alignement || "left",
    urgent: Boolean(offer.urgent),
    published: visibleOffer(offer),
    date: offer.date || ""
  };
}

function fromApiOffer(input, existing = {}) {
  return {
    ...existing,
    id: input.id ?? existing.id,
    titre: input.title ?? existing.titre ?? "",
    texte: input.description ?? existing.texte ?? "",
    notice: input.notice ?? existing.notice ?? "",
    categorie: input.category ?? existing.categorie ?? "",
    ville: input.city ?? existing.ville ?? "",
    banniere: input.banner ?? existing.banniere ?? "",
    boutons: Array.isArray(input.buttons) ? input.buttons : (Array.isArray(existing.boutons) ? existing.boutons : []),
    alignement: input.alignment ?? existing.alignement ?? "left",
    urgent: input.urgent ?? existing.urgent ?? false,
    publie: input.published ?? existing.publie ?? false,
    date: input.date ?? existing.date ?? new Date().toISOString()
  };
}
