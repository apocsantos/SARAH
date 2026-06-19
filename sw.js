const CACHE_STATIC = "sarah-aac-static-v2";
const CACHE_DYNAMIC = "sarah-aac-dynamic-v2";

const APP_SHELL = [
  "./",
  "./index.html",
  "./app.js",
  "./manifest.webmanifest",
  "./data/seed.json"
];

self.addEventListener("install", (event) => {
  event.waitUntil(caches.open(CACHE_STATIC).then(cache => cache.addAll(APP_SHELL)));
  self.skipWaiting();
});

self.addEventListener("activate", (event) => {
  event.waitUntil((async()=>{
    const names = await caches.keys();
    await Promise.all(names.filter(n => ![CACHE_STATIC, CACHE_DYNAMIC].includes(n)).map(n => caches.delete(n)));
    await self.clients.claim();
  })());
});

self.addEventListener("message", (event) => {
  if(event.data && event.data.type === "CLEAR_CACHES"){
    event.waitUntil(caches.keys().then(names => Promise.all(names.map(n => caches.delete(n)))));
  }
});

self.addEventListener("fetch", (event) => {
  const req = event.request;
  if(req.method !== "GET") return;
  const url = new URL(req.url);

  // Network-first for main code during development/updates, fallback cache for offline.
  if(url.origin === location.origin && /\/((index\.html)|(app\.js)|(manifest\.webmanifest)|(data\/seed\.json))$/.test(url.pathname)){
    event.respondWith(
      fetch(req, { cache: "no-store" }).then(res => {
        const copy = res.clone();
        caches.open(CACHE_STATIC).then(cache => cache.put(req, copy));
        return res;
      }).catch(() => caches.match(req))
    );
    return;
  }

  // Cache-first for local/static and icons.
  if(url.origin === location.origin){
    event.respondWith(caches.match(req).then(cached => cached || fetch(req).then(res => {
      const copy = res.clone();
      caches.open(CACHE_DYNAMIC).then(cache => cache.put(req, copy));
      return res;
    })));
    return;
  }

  // Stale-while-revalidate for remote seed/icons.
  event.respondWith(caches.open(CACHE_DYNAMIC).then(async cache => {
    const cached = await cache.match(req);
    const fetchPromise = fetch(req).then(res => {
      if(res && res.ok) cache.put(req, res.clone());
      return res;
    }).catch(() => cached);
    return cached || fetchPromise;
  }));
});
