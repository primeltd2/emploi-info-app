import { ZodError } from "zod";

export function notFoundHandler(req, res) {
  res.status(404).json({
    status: "error",
    message: "Route introuvable"
  });
}

export function errorHandler(err, req, res, next) {
  if (res.headersSent) return next(err);

  if (err instanceof ZodError) {
    return res.status(400).json({
      status: "error",
      message: "Parametres invalides",
      issues: err.issues.map((issue) => ({
        path: issue.path.join("."),
        message: issue.message
      }))
    });
  }

  const status = Number.isInteger(err.status) ? err.status : 500;
  res.status(status).json({
    status: "error",
    message: status >= 500 ? "Erreur serveur" : err.message
  });
}
