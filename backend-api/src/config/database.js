import pg from "pg";
import { env } from "./env.js";

const { Pool } = pg;

export const databaseEnabled = Boolean(env.DATABASE_URL);

export const pool = databaseEnabled
  ? new Pool({
      connectionString: env.DATABASE_URL,
      ssl: env.DATABASE_SSL ? { rejectUnauthorized: false } : undefined,
      max: env.DATABASE_POOL_MAX,
      idleTimeoutMillis: 30_000,
      connectionTimeoutMillis: 8_000
    })
  : null;

export async function query(text, params = []) {
  if (!pool) {
    throw new Error("DATABASE_URL is required for persistent storage");
  }
  return pool.query(text, params);
}

export async function withTransaction(fn) {
  if (!pool) {
    throw new Error("DATABASE_URL is required for persistent storage");
  }
  const client = await pool.connect();
  try {
    await client.query("BEGIN");
    const result = await fn(client);
    await client.query("COMMIT");
    return result;
  } catch (error) {
    await client.query("ROLLBACK");
    throw error;
  } finally {
    client.release();
  }
}
