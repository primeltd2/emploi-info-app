import { z } from "zod";

export const offersQuerySchema = z.object({
  limit: z.coerce.number().int().min(1).max(100).default(30),
  category: z.string().trim().min(1).max(80).optional(),
  city: z.string().trim().min(1).max(80).optional()
});

export const idParamSchema = z.object({
  id: z.string().trim().min(1).max(120)
});

export const appVersionQuerySchema = z.object({
  kind: z.enum(["public", "admin"]).default("public")
});

const buttonSchema = z.object({
  texte: z.string().trim().max(80).default(""),
  lien: z.string().trim().max(500).default(""),
  couleur: z.string().trim().max(40).optional()
}).passthrough();

export const offerCreateSchema = z.object({
  title: z.string().trim().min(3).max(220),
  description: z.string().trim().min(5).max(50000),
  notice: z.string().trim().max(1000).default(""),
  category: z.string().trim().max(80).default(""),
  city: z.string().trim().max(80).default(""),
  banner: z.string().trim().max(500).default(""),
  buttons: z.array(buttonSchema).max(10).default([]),
  alignment: z.enum(["left", "center", "right"]).default("left"),
  urgent: z.boolean().default(false),
  published: z.boolean().default(false)
});

export const offerUpdateSchema = offerCreateSchema.partial().refine(
  (value) => Object.keys(value).length > 0,
  { message: "Au moins un champ est requis" }
);

export const publishSchema = z.object({
  published: z.boolean()
});

export const androidTokenSchema = z.object({
  token: z.string().trim().min(20).max(500),
  platform: z.string().trim().max(40).default("android"),
  app: z.string().trim().max(80).default("emploi-info")
});
