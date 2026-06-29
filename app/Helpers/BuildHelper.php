<?php

declare(strict_types=1);

namespace App\Helpers;

/**
 * BuildHelper — utilidades para integración frontend/backend.
 *
 * Proporciona el BUILD_HASH para versionar el Service Worker en el layout.
 * El hash lo genera webpack (plugin BuildHashPlugin) y lo escribe en
 * storage/build_hash.txt al compilar.
 *
 * USO EN EL LAYOUT:
 *   <?php use App\Helpers\BuildHelper; ?>
 *   <script>
 *       BuildHelper::registerServiceWorker();
 *   </script>
 */
final class BuildHelper
{
    private static ?string $cachedHash = null;

    /**
     * Retorna el BUILD_HASH actual.
     *
     * En desarrollo (archivo no existe): retorna 'dev'.
     * En producción: retorna el hash de 8 chars generado por webpack.
     */
    public static function buildHash(): string
    {
        if (self::$cachedHash !== null) {
            return self::$cachedHash;
        }

        $hashFile = dirname(__DIR__, 2) . '/storage/build_hash.txt';

        if (is_readable($hashFile)) {
            $hash = trim((string) file_get_contents($hashFile));
            self::$cachedHash = $hash !== '' ? $hash : 'dev';
        } else {
            self::$cachedHash = 'dev';
        }

        return self::$cachedHash;
    }

    /**
     * Genera el tag <script> que registra el Service Worker con el hash correcto.
     *
     * Incluye:
     * - Detección de soporte (navigator.serviceWorker)
     * - Registro con ?v=BUILD_HASH para invalidar caché en deploy
     * - Listener de actualizaciones: avisa al usuario que hay versión nueva
     *
     * @param bool $showUpdateBanner  Mostrar banner de "nueva versión disponible"
     */
    public static function swRegistrationScript(bool $showUpdateBanner = true): string
    {
        $hash = htmlspecialchars(self::buildHash(), ENT_QUOTES, 'UTF-8');

        $bannerScript = $showUpdateBanner ? <<<'JS'
                registration.addEventListener('updatefound', () => {
                    const newWorker = registration.installing;
                    newWorker.addEventListener('statechange', () => {
                        if (newWorker.state === 'installed' && navigator.serviceWorker.controller) {
                            // Hay una nueva versión disponible
                            if (window.LegalOPS && typeof window.LegalOPS.notifyUpdate === 'function') {
                                window.LegalOPS.notifyUpdate();
                            } else {
                                console.info('[SW] Nueva versión disponible. Recarga para actualizar.');
                            }
                        }
                    });
                });
JS : '';

        return <<<HTML
<script>
(function () {
    'use strict';
    if (!('serviceWorker' in navigator)) return;

    window.addEventListener('load', function () {
        navigator.serviceWorker.register('/sw.js?v={$hash}', { scope: '/' })
            .then(function (registration) {
                {$bannerScript}
            })
            .catch(function (err) {
                console.warn('[SW] Registro fallido:', err);
            });
    });
}());
</script>
HTML;
    }

    /**
     * Fuerza que el SW instalado tome control inmediatamente (para el banner de update).
     * Llamar desde JS del cliente cuando el usuario acepta la actualización.
     */
    public static function swForceUpdateScript(): string
    {
        return <<<'HTML'
<script>
(function () {
    'use strict';
    if (!('serviceWorker' in navigator)) return;
    navigator.serviceWorker.ready.then(function (reg) {
        if (reg.waiting) {
            reg.waiting.postMessage({ type: 'SKIP_WAITING' });
            window.location.reload();
        }
    });
}());
</script>
HTML;
    }
}
