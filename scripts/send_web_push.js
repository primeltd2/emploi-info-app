const fs = require("fs");
const webpush = require("web-push");

async function main() {
  const inputPath = process.argv[2];
  if (!inputPath) throw new Error("Input JSON path required");

  const input = JSON.parse(fs.readFileSync(inputPath, "utf8"));
  webpush.setVapidDetails(
    input.vapid.subject,
    input.vapid.publicKey,
    input.vapid.privateKey
  );

  const payload = JSON.stringify(input.payload);
  const report = [];
  const kept = [];

  for (const sub of input.subscriptions || []) {
    if (!sub || !sub.endpoint || !sub.keys || !sub.keys.p256dh || !sub.keys.auth) continue;
    try {
      await webpush.sendNotification(sub, payload);
      report.push({ endpoint: sub.endpoint, success: true, reason: "Notification envoyee" });
      kept.push(sub);
    } catch (error) {
      const statusCode = error.statusCode || null;
      report.push({
        endpoint: sub.endpoint,
        success: false,
        statusCode,
        reason: error.body || error.message || "Erreur WebPush"
      });
      if (![404, 410].includes(statusCode)) kept.push(sub);
    }
  }

  process.stdout.write(JSON.stringify({
    status: "sent",
    count: report.length,
    report,
    kept
  }));
}

main().catch(error => {
  process.stdout.write(JSON.stringify({
    status: "node_web_push_error",
    count: 0,
    report: [],
    kept: [],
    msg: error.message || String(error)
  }));
  process.exitCode = 1;
});
