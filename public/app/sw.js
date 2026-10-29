/* Service worker MOK-Business : rend l'application utilisable hors ligne.
   On met en cache la coquille (HTML/CSS/JS/icône). Les appels API ne sont
   jamais mis en cache : les données doivent rester fraîches et sûres. */
const CACHE = 'mok-shell-v20';
const SHELL = [
  './',
  './index.html',
  './style.css',
  './app.js',
  './icon.svg',
  './manifest.webmanifest',
];

self.addEventListener('install', e => {
  e.waitUntil(caches.open(CACHE).then(c => c.addAll(SHELL)).then(() => self.skipWaiting()));
});

self.addEventListener('activate', e => {
  e.waitUntil(
    caches.keys().then(cles => Promise.all(cles.filter(k => k !== CACHE).map(k => caches.delete(k))))
      .then(() => self.clients.claim())
  );
});

self.addEventListener('fetch', e => {
  const req = e.request;
  const url = new URL(req.url);

  // Ne jamais mettre en cache l'API : on laisse passer vers le réseau.
  if (url.pathname.startsWith('/api/')) return;
  if (req.method !== 'GET') return;

  // Coquille : d'abord le cache, puis le réseau (et on rafraîchit le cache).
  e.respondWith(
    caches.match(req).then(cache => {
      const reseau = fetch(req).then(res => {
        if (res.ok && url.origin === location.origin) {
          const copie = res.clone();
          caches.open(CACHE).then(c => c.put(req, copie));
        }
        return res;
      }).catch(() => cache);
      return cache || reseau;
    })
  );
});
