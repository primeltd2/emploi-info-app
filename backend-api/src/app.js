import express from "express";
import { router } from "./routes/index.js";
import { errorHandler, notFoundHandler } from "./middlewares/errorHandler.js";
import { securityMiddleware } from "./middlewares/security.js";

export function createApp() {
  const app = express();

  app.disable("x-powered-by");
  app.use(express.json({ limit: "1mb" }));
  app.use(express.urlencoded({ extended: false, limit: "1mb" }));
  app.use(securityMiddleware());

  app.use("/api/v1", router);
  app.use(notFoundHandler);
  app.use(errorHandler);

  return app;
}
