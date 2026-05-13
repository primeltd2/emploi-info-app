const CACHE_NAME = "emploi-info-cache-v23";
const NOTIFICATION_SEEN_CACHE = "emploi-info-notification-seen-v1";
const ASSETS_TO_CACHE = [
  "/",
  "/index.html",
  "/manifest.json",
  "/app_version.json",
  "/logo.png",
  "/pwa-icon-192.png",
  "/pwa-icon-512.png",
  "/favicon.ico",
  "/banner.jpg",
  "/data.json",
  "/details.html",
  "/publicite-details.html",
  "/publicites.html",
  "/open-app.html",
  "/devenir-partenaire.html",
  "/faq.html",
  "/comment-ca-marche.html",
  "/formations.html",
  "/blog.html",
  "/services.html",
  "/ressources.html",
  "/resources.html",
  "/admin.html",
  "/formations.json",
  "/blog.json",
  "/resources.json",
  "/services.json",
  "/ads.json",
  "/assets/site-menu.js",
  "/assets/rich-content.js",
  "/assets/user-features.js",
  "/assets/user-features.css",
  "/assets/content-media.js",
  "/assets/interactions.css",
  "/assets/interactions.js",
  "/assets/access-control.js",
  "/stats.json"
];

self.addEventListener("install", event => {
  event.waitUntil(
    caches.open(CACHE_NAME).then(cache =>
      Promise.all(
        ASSETS_TO_CACHE.map(asset =>
          cache.add(asset).catch(() => null)
        )
      )
    )
  );
  self.skipWaiting();
});

self.addEventListener("activate", event => {
  event.waitUntil(
    caches.keys().then(keys =>
      Promise.all(keys.filter(k => k !== CACHE_NAME).map(k => caches.delete(k)))
    )
  );
  self.clients.claim();
});

self.addEventListener("fetch", event => {
  if (event.request.method !== "GET") return;

  const url = new URL(event.request.url);
  const isHtmlRequest =
    event.request.mode === "navigate" ||
    url.pathname.endsWith(".html") ||
    url.pathname === "/contact.html";
  const isDynamicJson =
    url.pathname.endsWith(".json") ||
    url.pathname.endsWith("/ads.json") ||
    url.pathname.endsWith("/data.json");
  const isNotificationSound = url.pathname.startsWith("/uploads/notifications/");

  if (isDynamicJson || isNotificationSound) {
    event.respondWith(
      fetch(event.request, { cache: "no-store" })
        .then(networkRes => {
          const clone = networkRes.clone();
          caches.open(CACHE_NAME).then(cache => {
            cache.put(event.request, clone).catch(() => {});
          });
          return networkRes;
        })
        .catch(() => caches.match(event.request))
    );
    return;
  }

  if (isHtmlRequest) {
    event.respondWith(
      fetch(event.request)
        .then(networkRes => {
          const clone = networkRes.clone();
          caches.open(CACHE_NAME).then(cache => {
            cache.put(event.request, clone).catch(() => {});
          });
          return networkRes;
        })
        .catch(() => caches.match(event.request).then(cacheRes => cacheRes || caches.match("/index.html")))
    );
    return;
  }

  event.respondWith(
    caches.match(event.request).then(cacheRes => {
      return (
        cacheRes ||
        fetch(event.request)
          .then(networkRes => {
            const clone = networkRes.clone();
            caches.open(CACHE_NAME).then(cache => {
              cache.put(event.request, clone).catch(() => {});
            });
            return networkRes;
          })
          .catch(() => {
            if (event.request.mode === "navigate") {
              return caches.match("/index.html");
            }
          })
      );
    })
  );
});

self.addEventListener("push", event => {
  if (!event.data) return;

  let data = {};
  try {
    data = event.data.json();
  } catch (e) {
    data = {
      title: "EMPLOI INFO",
      body: event.data.text()
    };
  }

  event.waitUntil((async () => {
    const notificationId = data.id ? String(data.id) : "";
    if (notificationId) {
      const seenCache = await caches.open(NOTIFICATION_SEEN_CACHE);
      const seenKey = new Request("/__notification_seen__/" + encodeURIComponent(notificationId));
      const seen = await seenCache.match(seenKey);
      if (seen) return;
      await seenCache.put(seenKey, new Response(JSON.stringify({seen_at: new Date().toISOString()})));
    }

    const options = {
      body: data.body || "Nouvelle offre disponible !",
      icon: data.icon || "/logo.png",
      badge: data.badge || "/logo.png",
      image: data.image || undefined,
      vibrate: [200, 100, 200],
      requireInteraction: true,
      renotify: false,
      tag: data.id ? `offer-${data.id}` : (data.url || "emploi-info"),
      data: {
        url: data.url || "/",
        id: data.id || "",
        sendNumber: data.sendNumber || 1
      }
    };

    if (self.registration.setAppBadge) {
      const badgeCount = Number(data.badgeCount || data.badge_count || 1);
      await self.registration.setAppBadge(Math.max(1, badgeCount)).catch(() => {});
    }

    await self.registration.showNotification(data.title || "EMPLOI INFO", options);
  })());
});

self.addEventListener("notificationclick", event => {
  event.notification.close();

  const url = event.notification.data?.url || "/";

  event.waitUntil(
    clients.matchAll({ type: "window", includeUncontrolled: true }).then(clientList => {
      for (const client of clientList) {
        if ("focus" in client) {
          client.navigate(url).catch(() => {});
          return client.focus();
        }
      }
      if (clients.openWindow) {
        return clients.openWindow(url);
      }
    })
  );
});
