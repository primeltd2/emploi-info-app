import { Router } from "express";
import { adminRouter } from "./admin.js";
import { androidTokenCreate } from "../controllers/androidController.js";
import { health } from "../controllers/healthController.js";
import {
  adsIndex,
  appVersionShow,
  catalogIndex,
  offersIndex,
  offersShow
} from "../controllers/catalogController.js";

export const router = Router();

router.get("/health", health);
router.get("/offers", offersIndex);
router.get("/offers/:id", offersShow);
router.get("/catalog", catalogIndex);
router.get("/ads", adsIndex);
router.get("/app-version", appVersionShow);
router.post("/android/tokens", androidTokenCreate);
router.use("/admin", adminRouter);
