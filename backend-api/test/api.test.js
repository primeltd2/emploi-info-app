import assert from "node:assert/strict";
import { test } from "node:test";
import { createApp } from "../src/app.js";

async function withServer(run) {
  const server = createApp().listen(0);
  await new Promise((resolve) => server.once("listening", resolve));
  const { port } = server.address();
  try {
    await run(`http://127.0.0.1:${port}`);
  } finally {
    await new Promise((resolve) => server.close(resolve));
  }
}

test("health endpoint responds", async () => {
  await withServer(async (baseUrl) => {
    const response = await fetch(`${baseUrl}/api/v1/health`);
    const body = await response.json();

    assert.equal(response.status, 200);
    assert.equal(body.status, "success");
    assert.equal(body.service, "emploi-info-api");
  });
});

test("offers endpoint returns public offers", async () => {
  await withServer(async (baseUrl) => {
    const response = await fetch(`${baseUrl}/api/v1/offers?limit=2`);
    const body = await response.json();

    assert.equal(response.status, 200);
    assert.equal(body.status, "success");
    assert.ok(Array.isArray(body.data));
    assert.ok(body.data.length <= 2);
  });
});

test("offers endpoint validates limit", async () => {
  await withServer(async (baseUrl) => {
    const response = await fetch(`${baseUrl}/api/v1/offers?limit=9999`);
    const body = await response.json();

    assert.equal(response.status, 400);
    assert.equal(body.status, "error");
  });
});

test("admin endpoints stay disabled without API_KEY", async () => {
  await withServer(async (baseUrl) => {
    const response = await fetch(`${baseUrl}/api/v1/admin/offers`);
    const body = await response.json();

    assert.equal(response.status, 503);
    assert.equal(body.status, "error");
  });
});
