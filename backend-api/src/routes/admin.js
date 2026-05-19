import { Router } from "express";
import { requireApiKey } from "../middlewares/adminAuth.js";
import {
  adminCatalogIndex,
  adminContentCreate,
  adminContentDelete,
  adminContentIndex,
  adminOffersCreate,
  adminOffersDelete,
  adminOffersIndex,
  adminOffersPublish,
  adminOffersUpdate,
  adminProcessNotificationReminders,
  adminCatalogListReplace
} from "../controllers/adminOfferController.js";

export const adminRouter = Router();

adminRouter.use(requireApiKey);
adminRouter.get("/offers", adminOffersIndex);
adminRouter.post("/offers", adminOffersCreate);
adminRouter.patch("/offers/:id", adminOffersUpdate);
adminRouter.patch("/offers/:id/publish", adminOffersPublish);
adminRouter.delete("/offers/:id", adminOffersDelete);
adminRouter.get("/catalog", adminCatalogIndex);
adminRouter.put("/catalog/:name", adminCatalogListReplace);
adminRouter.get("/content/:section", adminContentIndex);
adminRouter.post("/content/:section", adminContentCreate);
adminRouter.delete("/content/:section/:id", adminContentDelete);
adminRouter.post("/notifications/reminders/process", adminProcessNotificationReminders);
