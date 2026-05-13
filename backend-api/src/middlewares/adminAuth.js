import { env } from "../config/env.js";

export function requireApiKey(req, res, next) {
  if (!env.API_KEY) {
    return res.status(503).json({
      status: "error",
      message: "API admin non configuree"
    });
  }

  const header = req.get("x-api-key") || "";
  if (header !== env.API_KEY) {
    return res.status(401).json({
      status: "error",
      message: "Cle API invalide"
    });
  }

  next();
}
