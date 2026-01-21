const CACHE_STATIC = "aac-static-v1";
const CACHE_DYNAMIC = "aac-dynamic-v1";

const APP_SHELL = [
  "./",
  "./index.html",
  "./app.js",
  "./manifest.webmanifest",
  "./data/seed.json"
];

self.addEventListener("install", (event) => {
  event.waitUntil(
    caches.open(CACHE_STATIC).then(cache => cache.addAll(APP_SHELL))
  );
  self.skipWaiting();
});

self.addEventListener("activate", (event) => {
  event.waitUntil(self.clients.claim());
});

self.addEventListener("fetch", (event) => {
  const req = event.request;
  const url = new URL(req.url);
  if(req.method !== "GET") return;

  if(url.origin === location.origin){
    event.respondWith(
      caches.match(req).then(cached => {
        if(cached) return cached;
        return fetch(req).then(res => {
          const copy = res.clone();
          caches.open(CACHE_DYNAMIC).then(cache => cache.put(req, copy));
          return res;
        }).catch(() => cached);
      })
    );
    return;
  }

  event.respondWith(
    caches.open(CACHE_DYNAMIC).then(async cache => {
      const cached = await cache.match(req);
      const fetchPromise = fetch(req).then(res => {
        if(res && res.ok) cache.put(req, res.clone());
        return res;
      }).catch(() => cached);
      return cached || fetchPromise;
    })
  );
});
