'use strict';

// ── Cache versioning ─────────────────────────────────────────────────────────
//
// BUILD_HASH viene del parámetro ?v= en la URL de registro del SW:
//   navigator.serviceWorker.register('/sw.js?v=abc12345')
// Esto invalida el caché en cada deploy sin tocar este archivo.
// En desarrollo (sin parámetro) se usa 'dev'.
// ─────────────────────────────────────────────────────────────────────────────

const BUILD_HASH    = new URL(self.location.href).searchParams.get('v') || 'dev';
const STATIC_CACHE  = `legalops-static-${BUILD_HASH}`;
const DYNAMIC_CACHE = `legalops-dynamic-${BUILD_HASH}`;
const OFFLINE_URL   = '/offline';
const DYNAMIC_LIMIT = 50;

const STATIC_URLS = [
    '/offline',
    '/manifest.json',
    '/assets/icons/icon-192.png',
    '/assets/bootstrap/css/bootstrap.min.css',
    '/assets/icons/font/bootstrap-icons.min.css',
    '/assets/adminlte/css/adminlte.min.css',
    '/assets/css/app.css',
    '/assets/css/responsive.css',
    '/assets/css/mobile.css',
    '/assets/bootstrap/js/bootstrap.bundle.min.js',
    '/assets/adminlte/js/adminlte.min.js',
    '/assets/js/app.js',
    '/assets/js/improvements.js',
];

// ── Install: pre-cache static assets ────────────────────────────────────────
self.addEventListener('install', event => {
    event.waitUntil(
        caches.open(STATIC_CACHE)
            .then(cache => cache.addAll(STATIC_URLS))
            .then(() => self.skipWaiting()),
    );
});

// ── Activate: limpiar cachés anteriores y tomar control inmediato ─────────────
self.addEventListener('activate', event => {
    const validCaches = [STATIC_CACHE, DYNAMIC_CACHE];
    event.waitUntil(
        caches.keys()
            .then(keys =>
                Promise.all(
                    keys
                        .filter(k => k.startsWith('legalops-') && !validCaches.includes(k))
                        .map(k => caches.delete(k)),
                ),
            )
            .then(() => self.clients.claim()),
    );
});

// ── Helpers ──────────────────────────────────────────────────────────────────

/** Elimina entradas antiguas de DYNAMIC_CACHE si supera el límite FIFO. */
async function trimDynamicCache() {
    const cache = await caches.open(DYNAMIC_CACHE);
    const keys  = await cache.keys();
    if (keys.length > DYNAMIC_LIMIT) {
        const toDelete = keys.slice(0, keys.length - DYNAMIC_LIMIT);
        await Promise.all(toDelete.map(k => cache.delete(k)));
    }
}

/** Network fetch con timeout en milisegundos. Rechaza si excede el tiempo. */
function fetchWithTimeout(request, ms) {
    return new Promise((resolve, reject) => {
        const timer = setTimeout(() => reject(new Error('timeout')), ms);
        fetch(request)
            .then(res => { clearTimeout(timer); resolve(res); })
            .catch(err => { clearTimeout(timer); reject(err); });
    });
}

// ── Fetch: estrategia según tipo de request ──────────────────────────────────
self.addEventListener('fetch', event => {
    const { request } = event;
    const url = new URL(request.url);

    // Solo interceptar GET del mismo origen
    if (request.method !== 'GET') return;
    if (url.origin !== self.location.origin) return;

    // ── REGLA 1: API calls → network-only, JSON de error si falla ───────────
    if (url.pathname.startsWith('/api/')) {
        event.respondWith(
            fetch(request).catch(() =>
                new Response(
                    JSON.stringify({ ok: false, message: 'Sin conexión', offline: true }),
                    { status: 503, headers: { 'Content-Type': 'application/json' } },
                ),
            ),
        );
        return;
    }

    // ── REGLA 2: Assets estáticos → cache-first ──────────────────────────────
    const isStatic =
        url.pathname.startsWith('/assets/') ||
        url.pathname.startsWith('/icons/')  ||
        /\.(css|js|woff2?|ttf|svg|png|jpg|ico|webp)$/.test(url.pathname);

    if (isStatic) {
        event.respondWith(
            caches.match(request).then(cached => {
                if (cached) return cached;
                return fetch(request).then(response => {
                    if (!response.ok) return response;
                    const clone = response.clone();
                    caches.open(STATIC_CACHE).then(c => c.put(request, clone));
                    return response;
                });
            }),
        );
        return;
    }

    // ── REGLA 3: Navegación → network-first (3s timeout) + fallback offline ──
    if (request.mode === 'navigate') {
        event.respondWith(
            fetchWithTimeout(request, 3000)
                .then(response => {
                    if (!response.ok) return response;
                    const clone = response.clone();
                    caches.open(DYNAMIC_CACHE).then(async c => {
                        await c.put(request, clone);
                        await trimDynamicCache();
                    });
                    return response;
                })
                .catch(async () => {
                    const cached = await caches.match(request);
                    if (cached) return cached;
                    const offlinePage = await caches.match(OFFLINE_URL);
                    return offlinePage ?? new Response('Sin conexión', { status: 503 });
                }),
        );
        return;
    }

    // ── REGLA 4: Todo lo demás → network-first, caché dinámica como fallback ──
    event.respondWith(
        fetch(request)
            .then(response => {
                if (!response.ok) return response;
                const clone = response.clone();
                caches.open(DYNAMIC_CACHE).then(async c => {
                    await c.put(request, clone);
                    await trimDynamicCache();
                });
                return response;
            })
            .catch(() => caches.match(request)),
    );
});

// ── Message: forzar actualización desde el cliente ──────────────────────────
self.addEventListener('message', event => {
    if (event.data?.type === 'SKIP_WAITING') {
        self.skipWaiting();
    }
});
