import dotenv from "dotenv";
import path from "node:path";
import { fileURLToPath } from "node:url";
import { z } from "zod";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
dotenv.config({ path: path.resolve(__dirname, "../../.env") });

const schema = z.object({
  NODE_ENV: z.enum(["development", "test", "production"]).default("development"),
  PORT: z.coerce.number().int().positive().default(4000),
  APP_ORIGIN: z.string().default("http://localhost,http://127.0.0.1,https://emploi-info.page.gd,https://app.local,null"),
  DATA_DIR: z.string().default(".."),
  API_KEY: z.string().optional().default(""),
  FIREBASE_SERVICE_ACCOUNT_JSON: z.string().optional().default(""),
  FIREBASE_PROJECT_ID: z.string().optional().default(""),
  FIREBASE_CLIENT_EMAIL: z.string().optional().default(""),
  FIREBASE_PRIVATE_KEY: z.string().optional().default("")
});

const parsed = schema.parse(process.env);
const apiRoot = path.resolve(__dirname, "../..");

export const env = {
  ...parsed,
  API_ROOT: apiRoot,
  DATA_DIR_ABSOLUTE: path.resolve(apiRoot, parsed.DATA_DIR),
  APP_ORIGINS: parsed.APP_ORIGIN.split(",").map((origin) => origin.trim()).filter(Boolean)
};
