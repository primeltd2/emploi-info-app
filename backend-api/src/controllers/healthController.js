export function health(req, res) {
  res.json({
    status: "success",
    service: "emploi-info-api",
    uptime: process.uptime(),
    timestamp: new Date().toISOString()
  });
}
