import { dataPaths } from "../config/paths.js";
import { readJson, writeJson } from "../repositories/jsonRepository.js";

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
  return updateOffer(id, { published });
}

export async function deleteOffer(id) {
  const rows = normalizeArray(await readJson(dataPaths.offers));
  const nextRows = rows.filter((row) => String(row.id) !== String(id));
  if (nextRows.length === rows.length) return false;

  await writeJson(dataPaths.offers, nextRows);
  return true;
}

export async function getCatalog() {
  const [categories, cities, resources, services, blog] = await Promise.all([
    readJson(dataPaths.categories),
    readJson(dataPaths.cities),
    readJson(dataPaths.resources),
    readJson(dataPaths.services),
    readJson(dataPaths.blog)
  ]);

  return {
    categories: normalizeArray(categories),
    cities: normalizeArray(cities),
    resources: normalizeArray(resources),
    services: normalizeArray(services),
    blog: normalizeArray(blog)
  };
}

export async function getAds() {
  return normalizeArray(await readJson(dataPaths.ads));
}

export async function getAppVersion(kind = "public") {
  const file = kind === "admin" ? dataPaths.adminAppVersion : dataPaths.appVersion;
  return readJson(file, {});
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
