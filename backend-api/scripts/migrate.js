import fs from "node:fs/promises";
import path from "node:path";
import { fileURLToPath } from "node:url";
import { query, pool } from "../src/config/database.js";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const schema = await fs.readFile(path.resolve(__dirname, "../db/schema.sql"), "utf8");

await query(schema);
await pool.end();
console.log("PostgreSQL schema is ready.");
