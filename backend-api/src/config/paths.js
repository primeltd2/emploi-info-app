import path from "node:path";
import { env } from "./env.js";

export const dataPaths = {
  offers: path.join(env.DATA_DIR_ABSOLUTE, "data.json"),
  ads: path.join(env.DATA_DIR_ABSOLUTE, "ads.json"),
  categories: path.join(env.DATA_DIR_ABSOLUTE, "categories.json"),
  cities: path.join(env.DATA_DIR_ABSOLUTE, "villes.json"),
  resources: path.join(env.DATA_DIR_ABSOLUTE, "resources.json"),
  services: path.join(env.DATA_DIR_ABSOLUTE, "services.json"),
  blog: path.join(env.DATA_DIR_ABSOLUTE, "blog.json"),
  appVersion: path.join(env.DATA_DIR_ABSOLUTE, "app_version.json"),
  adminAppVersion: path.join(env.DATA_DIR_ABSOLUTE, "admin_app_version.json"),
  androidTokens: path.join(env.DATA_DIR_ABSOLUTE, "android_tokens.json")
};
