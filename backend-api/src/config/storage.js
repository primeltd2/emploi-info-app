import fs from "node:fs/promises";
import path from "node:path";
import { env } from "./env.js";
import { dataPaths } from "./paths.js";

const seededFiles = new Set();

export async function ensurePersistentStorage() {
  await fs.mkdir(env.DATA_DIR_ABSOLUTE, { recursive: true });

  await Promise.all(
    Object.values(dataPaths).map((targetPath) => seedDataFile(targetPath))
  );
}

export async function seedDataFile(targetPath) {
  if (seededFiles.has(targetPath)) return;

  const exists = await fs.stat(targetPath).then(() => true).catch(() => false);
  if (exists) {
    seededFiles.add(targetPath);
    return;
  }

  const sourcePath = path.join(env.SEED_DATA_DIR_ABSOLUTE, path.basename(targetPath));
  const source = await fs.readFile(sourcePath, "utf8").catch(() => null);

  await fs.mkdir(path.dirname(targetPath), { recursive: true });
  await fs.writeFile(targetPath, source ?? "[]\n", "utf8");
  seededFiles.add(targetPath);
}
