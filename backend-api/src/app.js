import express from "express";
import { router } from "./routes/index.js";
import { errorHandler, notFoundHandler } from "./middlewares/errorHandler.js";
import { securityMiddleware } from "./middlewares/security.js";

export function createApp() {
  const app = express();

  app.disable("x-powered-by");
  app.use(express.json({ limit: "8mb" }));
  app.use(express.urlencoded({ extended: false, limit: "8mb" }));
  app.use(securityMiddleware());

  app.get("/", (req, res) => {
    res.json({
      status: "success",
      service: "emploi-info-api",
      health: "/api/v1/health",
      api: "/api/v1"
    });
  });

  app.use("/api/v1", router);
  app.use(notFoundHandler);
  app.use(errorHandler);

  return app;
}
