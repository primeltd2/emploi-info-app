import {
  getAds,
  getAppVersion,
  getCatalog,
  getOffer,
  listOffers
} from "../services/catalogService.js";
import {
  appVersionQuerySchema,
  idParamSchema,
  offersQuerySchema
} from "../validators/queryValidators.js";

export async function offersIndex(req, res, next) {
  try {
    const query = offersQuerySchema.parse(req.query);
    const offers = await listOffers(query);
    res.json({ status: "success", count: offers.length, data: offers });
  } catch (err) {
    next(err);
  }
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

export async function appVersionShow(req, res, next) {
  try {
    const { kind } = appVersionQuerySchema.parse(req.query);
    res.json({ status: "success", data: await getAppVersion(kind) });
  } catch (err) {
    next(err);
  }
}
