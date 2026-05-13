import {
  createOffer,
  deleteOffer,
  listAdminOffers,
  setOfferPublished,
  updateOffer
} from "../services/catalogService.js";
import {
  idParamSchema,
  offerCreateSchema,
  offerUpdateSchema,
  publishSchema
} from "../validators/queryValidators.js";

export async function adminOffersIndex(req, res, next) {
  try {
    const offers = await listAdminOffers();
    res.json({ status: "success", count: offers.length, data: offers });
  } catch (err) {
    next(err);
  }
}

export async function adminOffersCreate(req, res, next) {
  try {
    const input = offerCreateSchema.parse(req.body);
    const offer = await createOffer(input);
    res.status(201).json({ status: "success", data: offer });
  } catch (err) {
    next(err);
  }
}

export async function adminOffersUpdate(req, res, next) {
  try {
    const { id } = idParamSchema.parse(req.params);
    const input = offerUpdateSchema.parse(req.body);
    const offer = await updateOffer(id, input);
    if (!offer) return res.status(404).json({ status: "error", message: "Annonce introuvable" });
    res.json({ status: "success", data: offer });
  } catch (err) {
    next(err);
  }
}

export async function adminOffersPublish(req, res, next) {
  try {
    const { id } = idParamSchema.parse(req.params);
    const { published } = publishSchema.parse(req.body);
    const offer = await setOfferPublished(id, published);
    if (!offer) return res.status(404).json({ status: "error", message: "Annonce introuvable" });
    res.json({ status: "success", data: offer });
  } catch (err) {
    next(err);
  }
}

export async function adminOffersDelete(req, res, next) {
  try {
    const { id } = idParamSchema.parse(req.params);
    const deleted = await deleteOffer(id);
    if (!deleted) return res.status(404).json({ status: "error", message: "Annonce introuvable" });
    res.status(204).send();
  } catch (err) {
    next(err);
  }
}
