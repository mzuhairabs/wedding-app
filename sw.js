const CACHE_NAME = 'studio-manager-v2';
const ASSETS_TO_CACHE = [
  './',
  './index.php',
  './booking.php',
  './expenses.php',
  './packages.php',
  './crew.php',
  './progress_report.php',
  './reminders.php',
  './manifest.json',
  'https://cdn.tailwindcss.com'
];

// 1. Install Event - Cache assets penting
self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME).then((cache) => {
      console.log('[SW] Caching core assets');
      return cache.addAll(ASSETS_TO_CACHE);
    })
  );
  self.skipWaiting();
});

// 2. Activate Event - Bersihkan cache lama
self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((cacheNames) => {
      return Promise.all(
        cacheNames.map((cache) => {
          if (cache !== CACHE_NAME) {
            console.log('[SW] Deleting old cache:', cache);
            return caches.delete(cache);
          }
        })
      );
    })
  );
  self.clients.claim();
});

// 3. Fetch Event - Capaian Rangkaian Dulu, jika Offline ambil dari Cache
self.addEventListener('fetch', (event) => {
  event.respondWith(
    fetch(event.request).catch(() => {
      return caches.match(event.request);
    })
  );
});