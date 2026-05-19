const clients = new Set();

export function subscribeToOfferEvents(res) {
  res.setHeader("Content-Type", "text/event-stream");
  res.setHeader("Cache-Control", "no-cache, no-transform");
  res.setHeader("Connection", "keep-alive");
  res.flushHeaders?.();
  res.write("retry: 5000\n\n");

  clients.add(res);
  const heartbeat = setInterval(() => {
    res.write(`event: ping\ndata: ${JSON.stringify({ now: new Date().toISOString() })}\n\n`);
  }, 25_000);

  return () => {
    clearInterval(heartbeat);
    clients.delete(res);
  };
}

export function publishOfferEvent(type, offer) {
  const payload = JSON.stringify({ type, offer, at: new Date().toISOString() });
  for (const res of clients) {
    res.write("event: offer\n");
    res.write(`data: ${payload}\n\n`);
  }
}
