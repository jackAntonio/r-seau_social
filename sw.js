const CACHE = 'rs-v1';
const STATIC = [
  '/accueil.php',
  '/login.php',
  '/default-user.png',
  '/manifest.json'
];

// Installation : mise en cache des assets statiques
self.addEventListener('install', e => {
  e.waitUntil(
    caches.open(CACHE).then(c => c.addAll(STATIC)).then(() => self.skipWaiting())
  );
});

// Activation : purge des anciens caches
self.addEventListener('activate', e => {
  e.waitUntil(
    caches.keys().then(keys =>
      Promise.all(keys.filter(k => k !== CACHE).map(k => caches.delete(k)))
    ).then(() => self.clients.claim())
  );
});

// Stratégie Network-first pour les requêtes PHP (données dynamiques)
// Cache-first pour les assets statiques
self.addEventListener('fetch', e => {
  const url = new URL(e.request.url);

  // Ignorer les SSE et les requêtes cross-origin
  if (url.pathname.includes('sse_') || e.request.url.startsWith('chrome-extension')) return;

  // Assets statiques : cache-first
  if (/\.(png|jpg|gif|webp|css|js|woff2?)$/.test(url.pathname)) {
    e.respondWith(
      caches.match(e.request).then(cached => cached || fetch(e.request).then(res => {
        if (res.ok) {
          const clone = res.clone();
          caches.open(CACHE).then(c => c.put(e.request, clone));
        }
        return res;
      }))
    );
    return;
  }

  // Pages PHP : network-first avec fallback cache
  e.respondWith(
    fetch(e.request)
      .then(res => {
        if (res.ok && e.request.method === 'GET') {
          const clone = res.clone();
          caches.open(CACHE).then(c => c.put(e.request, clone));
        }
        return res;
      })
      .catch(() => caches.match(e.request))
  );
});

// Push notifications
self.addEventListener('push', e => {
  const data = e.data ? e.data.json() : { title: 'Réseau Social', body: 'Nouvelle notification' };
  e.waitUntil(
    self.registration.showNotification(data.title || 'Réseau Social', {
      body:    data.body  || '',
      icon:    '/icons/icon-192.png',
      badge:   '/icons/icon-192.png',
      data:    { url: data.url || '/accueil.php' }
    })
  );
});

self.addEventListener('notificationclick', e => {
  e.notification.close();
  e.waitUntil(clients.openWindow(e.notification.data.url));
});
