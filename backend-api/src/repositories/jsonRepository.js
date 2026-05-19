import fs from "node:fs/promises";
import path from "node:path";
import { seedDataFile } from "../config/storage.js";

const cache = new Map();

export async function readJson(filePath, fallback = []) {
  const stat = await fs.stat(filePath).catch(() => null);
  if (!stat) {
    await seedDataFile(filePath);
  }

  const nextStat = stat || await fs.stat(filePath).catch(() => null);
  if (!nextStat) return fallback;

  const cached = cache.get(filePath);
  if (cached && cached.mtimeMs === nextStat.mtimeMs) return cached.value;

  const raw = await fs.readFile(filePath, "utf8");
  const value = raw.trim() === "" ? fallback : JSON.parse(raw);
  cache.set(filePath, { mtimeMs: nextStat.mtimeMs, value });
  return value;
}

export async function writeJson(filePath, value) {
  const dir = path.dirname(filePath);
  await fs.mkdir(dir, { recursive: true });

  const tempPath = `${filePath}.${process.pid}.${Date.now()}.tmp`;
  const json = `${JSON.stringify(value, null, 2)}\n`;
  await fs.writeFile(tempPath, json, "utf8");
  await fs.rename(tempPath, filePath);

  cache.delete(filePath);
  return value;
}
