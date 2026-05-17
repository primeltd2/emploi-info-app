import assert from "node:assert/strict";
import fs from "node:fs/promises";
import os from "node:os";
import path from "node:path";
import { test } from "node:test";
import { createApp } from "../src/app.js";
import { env } from "../src/config/env.js";
import { dataPaths } from "../src/config/paths.js";

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

test("root endpoint helps Render checks", async () => {
  await withServer(async (baseUrl) => {
    const response = await fetch(`${baseUrl}/`);
    const body = await response.json();

    assert.equal(response.status, 200);
    assert.equal(body.status, "success");
    assert.equal(body.health, "/api/v1/health");
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
  const originalApiKey = env.API_KEY;
  env.API_KEY = "";

  try {
    await withServer(async (baseUrl) => {
      const response = await fetch(`${baseUrl}/api/v1/admin/offers`);
      const body = await response.json();

      assert.equal(response.status, 503);
      assert.equal(body.status, "error");
    });
  } finally {
    env.API_KEY = originalApiKey;
  }
});

test("admin published offer is visible in public app feed", async () => {
  const originalApiKey = env.API_KEY;
  const originalOffersPath = dataPaths.offers;
  const tmpDir = await fs.mkdtemp(path.join(os.tmpdir(), "emploi-info-api-"));
  dataPaths.offers = path.join(tmpDir, "data.json");
  env.API_KEY = "test-admin-key";

  try {
    await fs.writeFile(dataPaths.offers, "[]\n", "utf8");

    await withServer(async (baseUrl) => {
      const createResponse = await fetch(`${baseUrl}/api/v1/admin/offers`, {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          "x-api-key": env.API_KEY
        },
        body: JSON.stringify({
          title: "Annonce publiee depuis admin",
          description: "Cette annonce doit apparaitre dans l'application publique.",
          category: "Emploi",
          city: "Cotonou",
          published: true
        })
      });
      const created = await createResponse.json();

      assert.equal(createResponse.status, 201);
      assert.equal(created.status, "success");

      const publicResponse = await fetch(`${baseUrl}/api/v1/offers?limit=10`);
      const publicBody = await publicResponse.json();

      assert.equal(publicResponse.status, 200);
      assert.equal(publicBody.status, "success");
      assert.ok(
        publicBody.data.some((offer) => offer.id === created.data.id && offer.title === "Annonce publiee depuis admin")
      );
    });
  } finally {
    env.API_KEY = originalApiKey;
    dataPaths.offers = originalOffersPath;
    await fs.rm(tmpDir, { recursive: true, force: true });
  }
});

test("admin can delete an offer from the public feed", async () => {
  const originalApiKey = env.API_KEY;
  const originalOffersPath = dataPaths.offers;
  const tmpDir = await fs.mkdtemp(path.join(os.tmpdir(), "emploi-info-api-"));
  dataPaths.offers = path.join(tmpDir, "data.json");
  env.API_KEY = "test-admin-key";

  try {
    await fs.writeFile(dataPaths.offers, JSON.stringify([
      { id: "offer-to-delete", titre: "A supprimer", texte: "Visible avant suppression", publie: true }
    ]), "utf8");

    await withServer(async (baseUrl) => {
      const deleteResponse = await fetch(`${baseUrl}/api/v1/admin/offers/offer-to-delete`, {
        method: "DELETE",
        headers: { "x-api-key": env.API_KEY }
      });

      assert.equal(deleteResponse.status, 204);

      const publicResponse = await fetch(`${baseUrl}/api/v1/offers?limit=10`);
      const publicBody = await publicResponse.json();

      assert.equal(publicBody.status, "success");
      assert.equal(publicBody.data.some((offer) => offer.id === "offer-to-delete"), false);
    });
  } finally {
    env.API_KEY = originalApiKey;
    dataPaths.offers = originalOffersPath;
    await fs.rm(tmpDir, { recursive: true, force: true });
  }
});

test("admin can update remote categories", async () => {
  const originalApiKey = env.API_KEY;
  const originalCategoriesPath = dataPaths.categories;
  const originalCitiesPath = dataPaths.cities;
  const tmpDir = await fs.mkdtemp(path.join(os.tmpdir(), "emploi-info-api-"));
  dataPaths.categories = path.join(tmpDir, "categories.json");
  dataPaths.cities = path.join(tmpDir, "villes.json");
  env.API_KEY = "test-admin-key";

  try {
    await fs.writeFile(dataPaths.categories, "[]\n", "utf8");
    await fs.writeFile(dataPaths.cities, "[]\n", "utf8");

    await withServer(async (baseUrl) => {
      const saveResponse = await fetch(`${baseUrl}/api/v1/admin/catalog/categories`, {
        method: "PUT",
        headers: {
          "Content-Type": "application/json",
          "x-api-key": env.API_KEY
        },
        body: JSON.stringify({ items: ["Emploi", "Formation", "Emploi"] })
      });
      const saveBody = await saveResponse.json();

      assert.equal(saveResponse.status, 200);
      assert.deepEqual(saveBody.data, ["Emploi", "Formation"]);

      const catalogResponse = await fetch(`${baseUrl}/api/v1/catalog`);
      const catalogBody = await catalogResponse.json();

      assert.equal(catalogBody.status, "success");
      assert.deepEqual(catalogBody.data.categories, ["Emploi", "Formation"]);
    });
  } finally {
    env.API_KEY = originalApiKey;
    dataPaths.categories = originalCategoriesPath;
    dataPaths.cities = originalCitiesPath;
    await fs.rm(tmpDir, { recursive: true, force: true });
  }
});
