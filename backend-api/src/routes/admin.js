import { Router } from "express";
import { requireApiKey } from "../middlewares/adminAuth.js";
import {
  adminOffersCreate,
  adminOffersDelete,
  adminOffersIndex,
  adminOffersPublish,
  adminOffersUpdate
} from "../controllers/adminOfferController.js";

export const adminRouter = Router();

adminRouter.use(requireApiKey);
adminRouter.get("/offers", adminOffersIndex);
adminRouter.post("/offers", adminOffersCreate);
adminRouter.patch("/offers/:id", adminOffersUpdate);
adminRouter.patch("/offers/:id/publish", adminOffersPublish);
adminRouter.delete("/offers/:id", adminOffersDelete);
