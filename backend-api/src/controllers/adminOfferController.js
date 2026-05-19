import {
  createOffer,
  createContentSectionItem,
  deleteOffer,
  deleteContentSectionItem,
  getCatalog,
  listContentSection,
  listAdminOffers,
  replaceCatalogList,
  setOfferPublished,
  updateOffer
} from "../services/catalogService.js";
import {
  catalogListSchema,
  contentItemCreateSchema,
  contentSectionParamSchema,
  idParamSchema,
  offerCreateSchema,
  offerUpdateSchema,
  publishSchema
} from "../validators/queryValidators.js";
import { processDueOfferReminders } from "../services/notificationService.js";

export async function adminOffersIndex(req, res, next) {
  try {
    const offers = await listAdminOffers();
    res.json({ status: "success", count: offers.length, data: offers });
  } catch (err) {
    next(err);
  }
}

export async function adminCatalogIndex(req, res, next) {
  try {
    res.json({ status: "success", data: await getCatalog() });
  } catch (err) {
    next(err);
  }
}

export async function adminContentIndex(req, res, next) {
  try {
    const { section } = contentSectionParamSchema.parse(req.params);
    const data = await listContentSection(section);
    res.json({ status: "success", count: data.length, data });
  } catch (err) {
    next(err);
  }
}

export async function adminContentCreate(req, res, next) {
  try {
    const { section } = contentSectionParamSchema.parse(req.params);
    const input = contentItemCreateSchema.parse(req.body);
    const item = await createContentSectionItem(section, input);
    res.status(201).json({ status: "success", data: item });
  } catch (err) {
    next(err);
  }
}

export async function adminContentDelete(req, res, next) {
  try {
    const { section } = contentSectionParamSchema.parse(req.params);
    const { id } = idParamSchema.parse(req.params);
    const deleted = await deleteContentSectionItem(section, id);
    if (!deleted) return res.status(404).json({ status: "error", message: "Publication introuvable" });
    res.status(204).send();
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

export async function adminCatalogListReplace(req, res, next) {
  try {
    const { name } = req.params;
    const { items } = catalogListSchema.parse(req.body);
    const data = await replaceCatalogList(name, items);
    if (!data) return res.status(404).json({ status: "error", message: "Catalogue introuvable" });
    res.json({ status: "success", count: data.length, data });
  } catch (err) {
    next(err);
  }
}

export async function adminProcessNotificationReminders(req, res, next) {
  try {
    const sent = await processDueOfferReminders();
    res.json({ status: "success", sent });
  } catch (err) {
    next(err);
  }
}
