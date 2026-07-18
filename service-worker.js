/* vegasroyalspin PWA service worker */
const SW_VERSION = 'v3-modal-header-fix';
const STATIC_CACHE = `vrs-static-${SW_VERSION}`;

const PRE_CACHE_URLS = [
  '/',
  '/assets/images/favicons/site.webmanifest',
  '/assets/images/favicons/favicon.svg',
  '/assets/images/favicons/favicon-32x32.png',
  '/assets/images/favicons/favicon-16x16.png',
  '/assets/images/favicons/apple-touch-icon.png',
  '/assets/images/favicons/android-chrome-192x192.png',
  '/assets/images/favicons/android-chrome-512x512.png'
];

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(STATIC_CACHE).then((cache) => cache.addAll(PRE_CACHE_URLS))
  );
  self.skipWaiting();
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((keys) =>
      Promise.all(
        keys
          .filter((key) => key.startsWith('vrs-static-') && key !== STATIC_CACHE)
          .map((key) => caches.delete(key))
      )
    )
  );
  self.clients.claim();
});

function isNavigationRequest(request) {
  return request.mode === 'navigate';
}

function isCacheableStatic(requestUrl) {
  if (requestUrl.origin !== self.location.origin) {
    return false;
  }

  return (
    requestUrl.pathname.startsWith('/assets/') ||
    requestUrl.pathname.endsWith('.webmanifest') ||
    requestUrl.pathname.endsWith('.css') ||
    requestUrl.pathname.endsWith('.js') ||
    requestUrl.pathname.endsWith('.png') ||
    requestUrl.pathname.endsWith('.jpg') ||
    requestUrl.pathname.endsWith('.jpeg') ||
    requestUrl.pathname.endsWith('.svg') ||
    requestUrl.pathname.endsWith('.ico')
  );
}

function isAuthAppAsset(requestUrl) {
  if (requestUrl.origin !== self.location.origin) {
    return false;
  }

  return (
    requestUrl.pathname.startsWith('/assets/js/auth-') ||
    requestUrl.pathname.startsWith('/assets/js/login') ||
    requestUrl.pathname.startsWith('/assets/js/register') ||
    requestUrl.pathname.startsWith('/assets/js/pwa-register') ||
    requestUrl.pathname.startsWith('/assets/css/login') ||
    requestUrl.pathname.startsWith('/assets/css/register') ||
    requestUrl.pathname.startsWith('/mobile/assets/css/auth-modals')
  );
}

function isCriticalModalAsset(requestUrl) {
  if (requestUrl.origin !== self.location.origin) {
    return false;
  }

  return (
    requestUrl.pathname.startsWith('/assets/js/bonus-detail-modal') ||
    requestUrl.pathname.startsWith('/assets/css/bonus-detail-modal') ||
    requestUrl.pathname.startsWith('/assets/js/promosyonlar')
  );
}

self.addEventListener('fetch', (event) => {
  const { request } = event;

  if (request.method !== 'GET') {
    return;
  }

  const requestUrl = new URL(request.url);

  if (isNavigationRequest(request)) {
    event.respondWith(
      fetch(request)
        .then((response) => response)
        .catch(() => caches.match('/'))
    );
    return;
  }

  if (!isCacheableStatic(requestUrl)) {
    return;
  }

  if (isCriticalModalAsset(requestUrl)) {
    event.respondWith(
      fetch(request)
        .then((response) => {
          if (response && response.status === 200) {
            const cloned = response.clone();
            caches.open(STATIC_CACHE).then((cache) => cache.put(request, cloned));
          }
          return response;
        })
        .catch(() => caches.match(request))
    );
    return;
  }

  if (isAuthAppAsset(requestUrl)) {
    event.respondWith(
      fetch(request)
        .then((response) => {
          if (response && response.status === 200) {
            const cloned = response.clone();
            caches.open(STATIC_CACHE).then((cache) => cache.put(request, cloned));
          }
          return response;
        })
        .catch(() => caches.match(request))
    );
    return;
  }

  event.respondWith(
    caches.match(request).then((cached) => {
      const networkFetch = fetch(request)
        .then((response) => {
          if (response && response.status === 200) {
            const cloned = response.clone();
            caches.open(STATIC_CACHE).then((cache) => cache.put(request, cloned));
          }
          return response;
        })
        .catch(() => cached);

      return cached || networkFetch;
    })
  );
});
