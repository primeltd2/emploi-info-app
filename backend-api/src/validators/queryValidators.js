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
  banner: z.string().trim().max(8000000).default(""),
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

export const catalogListSchema = z.object({
  items: z.array(z.string().trim().min(1).max(120)).max(500)
});

export const contentSectionParamSchema = z.object({
  section: z.enum(["blog", "resources", "services", "formations"])
});

export const contentItemCreateSchema = z.object({
  titre: z.string().trim().min(2).max(220),
  texte: z.string().trim().min(2).max(50000),
  categorie: z.string().trim().max(120).default(""),
  lieu: z.string().trim().max(160).default(""),
  prix: z.string().trim().max(80).default(""),
  pricing_type: z.string().trim().max(40).default(""),
  prix_normal: z.string().trim().max(80).default(""),
  prix_promo: z.string().trim().max(80).default(""),
  promo_mode: z.string().trim().max(40).default(""),
  promo_until: z.string().trim().max(40).default(""),
  promo_places: z.string().trim().max(40).default(""),
  date_debut: z.string().trim().max(40).default(""),
  lien: z.string().trim().max(800).default(""),
  img: z.string().trim().max(8000000).default(""),
  boutons: z.array(buttonSchema).max(10).default([])
});

export const androidTokenSchema = z.object({
  token: z.string().trim().min(20).max(500),
  platform: z.string().trim().max(40).default("android"),
  app: z.string().trim().max(80).default("emploi-info")
});

export const commentCreateSchema = z.object({
  item_type: z.string().trim().max(40).default("annonce"),
  item_id: z.string().trim().min(1).max(120),
  text: z.string().trim().min(1).max(1200),
  visitor_id: z.string().trim().max(180).optional(),
  username: z.string().trim().max(40).optional()
});
