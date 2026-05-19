import { Router } from "express";
import { adminRouter } from "./admin.js";
import { androidTokenCreate } from "../controllers/androidController.js";
import { health } from "../controllers/healthController.js";
import { commentCreate } from "../controllers/interactionController.js";
import {
  adsIndex,
  appVersionShow,
  catalogIndex,
  contentIndex,
  offersIndex,
  settingsShow,
  offersStream,
  offersShow
} from "../controllers/catalogController.js";

export const router = Router();

router.get("/health", health);
router.get("/offers", offersIndex);
router.get("/offers/stream", offersStream);
router.get("/offers/:id", offersShow);
router.get("/catalog", catalogIndex);
router.get("/content/:section", contentIndex);
router.get("/ads", adsIndex);
router.get("/settings", settingsShow);
router.get("/app-version", appVersionShow);
router.post("/android/tokens", androidTokenCreate);
router.post("/interactions/comment", commentCreate);
router.use("/admin", adminRouter);
