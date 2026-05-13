import cors from "cors";
import helmet from "helmet";
import rateLimit from "express-rate-limit";
import { env } from "../config/env.js";

export function securityMiddleware() {
  const corsMiddleware = cors({
    origin(origin, callback) {
      if (!origin || env.APP_ORIGINS.includes(origin)) return callback(null, true);
      return callback(new Error("Origine non autorisee"));
    }
  });

  const limiter = rateLimit({
    windowMs: 60 * 1000,
    limit: 120,
    standardHeaders: "draft-8",
    legacyHeaders: false
  });

  return [
    helmet({
      crossOriginResourcePolicy: { policy: "cross-origin" }
    }),
    corsMiddleware,
    limiter
  ];
}
