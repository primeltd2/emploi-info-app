import { dataPaths } from "../config/paths.js";
import { databaseEnabled } from "../config/database.js";
import { readJson, writeJson } from "../repositories/jsonRepository.js";
import {
  createOfferRow,
  deleteOfferRow,
  getCatalogRows,
  getOfferRow,
  getSettingRow,
  createContentRow,
  deleteContentRow,
  listAdsRows,
  listContentRows,
  listOffersRows,
  replaceCatalogRows,
  saveAndroidTokenRow,
  upsertAdsRows,
  updateOfferRow
} from "../repositories/postgresRepository.js";
import {
  queueOfferReminder,
  sendAndroidOfferNotification,
  wasOfferNotificationSent
} from "./notificationService.js";
import { publishOfferEvent } from "./realtimeService.js";

function normalizeArray(value) {
  return Array.isArray(value) ? value : [];
}

function visibleOffer(offer) {
  return offer && (
    offer.published === true ||
    offer.publie === true ||
    offer.publie === "true" ||
    offer.publie === 1
  );
}

export async function listOffers({ limit = 30, category, city, includeDrafts = false } = {}) {
  if (databaseEnabled) {
    return (await listOffersRows({ limit, category, city, includeDrafts })).map(toPublicOffer);
  }
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
  if (databaseEnabled) {
    const offer = await getOfferRow(id, { includeDrafts });
    return offer ? toPublicOffer(offer) : null;
  }
  const rows = normalizeArray(await readJson(dataPaths.offers));
  const offer = rows.find((row) => String(row.id) === String(id));
  if (!offer || (!includeDrafts && !visibleOffer(offer))) return null;
  return toPublicOffer(offer);
}

export async function listAdminOffers({ limit = 100 } = {}) {
  if (databaseEnabled) {
    return (await listOffersRows({ limit, includeDrafts: true })).map(toPublicOffer);
  }
  const rows = normalizeArray(await readJson(dataPaths.offers));
  return rows.slice(0, limit).map(toPublicOffer);
}

export async function createOffer(input) {
  if (databaseEnabled) {
    const offer = await createOfferRow(input);
    if (visibleOffer(offer)) await notifyPublishedOffer(offer);
    if (visibleOffer(offer)) publishOfferEvent("created", toPublicOffer(offer));
    return toPublicOffer(offer);
  }
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
  if (visibleOffer(offer)) publishOfferEvent("created", toPublicOffer(offer));
  return toPublicOffer(offer);
}

export async function updateOffer(id, input) {
  if (databaseEnabled) {
    const before = await getOfferRow(id, { includeDrafts: true });
    const offer = await updateOfferRow(id, input);
    if (offer && visibleOffer(offer) && !visibleOffer(before)) await notifyPublishedOffer(offer);
    if (offer && visibleOffer(offer)) publishOfferEvent("updated", toPublicOffer(offer));
    return offer ? toPublicOffer(offer) : null;
  }
  const rows = normalizeArray(await readJson(dataPaths.offers));
  const index = rows.findIndex((row) => String(row.id) === String(id));
  if (index < 0) return null;

  rows[index] = {
    ...rows[index],
    ...fromApiOffer({ ...toPublicOffer(rows[index]), ...input, id: rows[index].id }, rows[index])
  };
  await writeJson(dataPaths.offers, rows);
  if (visibleOffer(rows[index])) publishOfferEvent("updated", toPublicOffer(rows[index]));
  return toPublicOffer(rows[index]);
}

export async function setOfferPublished(id, published) {
  if (databaseEnabled) return updateOffer(id, { published });
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
  if (databaseEnabled) return deleteOfferRow(id);
  const rows = normalizeArray(await readJson(dataPaths.offers));
  const nextRows = rows.filter((row) => String(row.id) !== String(id));
  if (nextRows.length === rows.length) return false;

  await writeJson(dataPaths.offers, nextRows);
  return true;
}

export async function getCatalog() {
  if (databaseEnabled) {
    const catalog = await getCatalogRows();
    return {
      categories: normalizeArray(catalog.categories),
      cities: normalizeArray(catalog.cities),
      resources: normalizeArray(catalog.resources),
      services: normalizeArray(catalog.services),
      blog: normalizeArray(catalog.blog),
      formations: normalizeArray(catalog.formations)
    };
  }
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
  if (databaseEnabled) return replaceCatalogRows(name, normalized);
  await writeJson(filePath, normalized);
  return normalized;
}

export async function getAds() {
  if (databaseEnabled) return listAdsRows();
  return normalizeArray(await readJson(dataPaths.ads));
}

export async function getAppVersion(kind = "public") {
  if (databaseEnabled) return getSettingRow(kind === "admin" ? "admin_app_version" : "app_version", {});
  const file = kind === "admin" ? dataPaths.adminAppVersion : dataPaths.appVersion;
  return readJson(file, {});
}

export async function getSettings() {
  if (databaseEnabled) return getSettingRow("params", {});
  return readJson(dataPaths.params, {});
}

export async function saveAndroidToken(input) {
  if (databaseEnabled) return saveAndroidTokenRow(input);
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
  if (databaseEnabled) return listContentRows(section);
  const filePath = sectionFilePath(section);
  if (!filePath) return null;
  return normalizeArray(await readJson(filePath));
}

export async function createContentSectionItem(section, input) {
  if (databaseEnabled) return createContentRow(section, input);
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
  if (databaseEnabled) return deleteContentRow(section, id);
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
    title: offer.title || offer.titre || "",
    description: offer.description || offer.texte || "",
    notice: offer.notice || "",
    category: offer.category || offer.categorie || "",
    city: offer.city || offer.ville || "",
    banner: offer.banner || offer.banniere || "",
    buttons: Array.isArray(offer.buttons) ? offer.buttons : (Array.isArray(offer.boutons) ? offer.boutons : []),
    alignment: offer.alignment || offer.alignement || "left",
    urgent: Boolean(offer.urgent),
    published: visibleOffer(offer),
    date: offer.published_at || offer.created_at || offer.date || ""
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
