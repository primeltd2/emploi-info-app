import {
  getAds,
  getAppVersion,
  getCatalog,
  getOffer,
  getSettings,
  listContentSection,
  listOffers
} from "../services/catalogService.js";
import { contentSectionParamSchema } from "../validators/queryValidators.js";
import { subscribeToOfferEvents } from "../services/realtimeService.js";
import {
  appVersionQuerySchema,
  idParamSchema,
  offersQuerySchema
} from "../validators/queryValidators.js";

export async function offersIndex(req, res, next) {
  try {
    res.set("Cache-Control", "no-store, max-age=0");
    const query = offersQuerySchema.parse(req.query);
    const offers = await listOffers(query);
    res.json({ status: "success", count: offers.length, data: offers });
  } catch (err) {
    next(err);
  }
}

export function offersStream(req, res) {
  const unsubscribe = subscribeToOfferEvents(res);
  req.on("close", unsubscribe);
}

export async function offersShow(req, res, next) {
  try {
    const { id } = idParamSchema.parse(req.params);
    const offer = await getOffer(id);
    if (!offer) return res.status(404).json({ status: "error", message: "Annonce introuvable" });
    res.json({ status: "success", data: offer });
  } catch (err) {
    next(err);
  }
}

export async function catalogIndex(req, res, next) {
  try {
    res.json({ status: "success", data: await getCatalog() });
  } catch (err) {
    next(err);
  }
}

export async function adsIndex(req, res, next) {
  try {
    const ads = await getAds();
    res.json({ status: "success", count: ads.length, data: ads });
  } catch (err) {
    next(err);
  }
}

export async function contentIndex(req, res, next) {
  try {
    const { section } = contentSectionParamSchema.parse(req.params);
    const data = await listContentSection(section);
    res.set("Cache-Control", "no-store, max-age=0");
    res.json({ status: "success", count: data.length, data });
  } catch (err) {
    next(err);
  }
}

export async function settingsShow(req, res, next) {
  try {
    res.set("Cache-Control", "no-store, max-age=0");
    res.json({ status: "success", data: await getSettings() });
  } catch (err) {
    next(err);
  }
}

export async function appVersionShow(req, res, next) {
  try {
    const { kind } = appVersionQuerySchema.parse(req.query);
    res.json({ status: "success", data: await getAppVersion(kind) });
  } catch (err) {
    next(err);
  }
}
