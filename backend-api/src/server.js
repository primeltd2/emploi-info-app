import { createApp } from "./app.js";
import { env } from "./config/env.js";
import { ensurePersistentStorage } from "./config/storage.js";

await ensurePersistentStorage();

const app = createApp();
app.listen(env.PORT, () => {
  console.log(`EMPLOI INFO API listening on port ${env.PORT}`);
});
