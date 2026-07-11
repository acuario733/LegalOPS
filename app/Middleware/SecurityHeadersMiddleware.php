<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;

/**
 * Agrega headers de seguridad HTTP a todas las respuestas.
 *
 * Alternativa programática al bloque mod_headers del .htaccess.
 * Útil en entornos Nginx o cuando mod_headers no está disponible.
 *
 * Activar en bootstrap registrando este middleware global:
 *   $router->middleware(new SecurityHeadersMiddleware());
 *
 * IMPORTANTE: HSTS solo debe activarse en producción con SSL válido.
 * Usar la variable de entorno APP_ENV=production para habilitarlo.
 */
final class SecurityHeadersMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, callable $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        // Previene MIME sniffing → drive-by-download, XSS via SVG
        $response = $response->withHeader('X-Content-Type-Options', 'nosniff');

        // Previene Clickjacking → embedding en iframes externos
        $response = $response->withHeader('X-Frame-Options', 'SAMEORIGIN');

        // Filtro XSS de browsers legacy (IE/Edge antiguo)
        $response = $response->withHeader('X-XSS-Protection', '1; mode=block');

        // Controla header Referer → evita filtrar rutas internas
        $response = $response->withHeader('Referrer-Policy', 'strict-origin-when-cross-origin');

        // Deshabilita APIs de hardware sensible
        $response = $response->withHeader(
            'Permissions-Policy',
            'geolocation=(), microphone=(), camera=(), payment=(), usb=()'
        );

        // Content Security Policy
        // 'unsafe-inline' requerido por AdminLTE/Bootstrap (estilos y scripts inline)
        // Considerar migrar a nonces en el futuro para eliminar 'unsafe-inline'
        $csp = implode('; ', [
            "default-src 'self'",
            "script-src 'self' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com 'unsafe-inline'",
            "style-src 'self' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com https://fonts.googleapis.com 'unsafe-inline'",
            "font-src 'self' https://fonts.gstatic.com https://cdnjs.cloudflare.com data:",
            "img-src 'self' data: blob:",
            "connect-src 'self'",
            "worker-src 'self'",
            "manifest-src 'self'",
            "frame-ancestors 'self'",
            "base-uri 'self'",
            "form-action 'self'",
        ]);
        $response = $response->withHeader('Content-Security-Policy', $csp);

        // HSTS solo en producción con SSL — previene downgrade attacks
        if (getenv('APP_ENV') === 'production') {
            $response = $response->withHeader(
                'Strict-Transport-Security',
                'max-age=31536000; includeSubDomains; preload'
            );
        }

        // Cache-Control para respuestas HTML dinámicas (autenticadas)
        $headers = $response->headers();
        $contentType = $headers['Content-Type'] ?? $headers['content-type'] ?? '';
        if (str_contains($contentType, 'text/html') || $contentType === '') {
            $response = $response->withHeader('Cache-Control', 'no-store, no-cache, must-revalidate');
        }

        return $response;
    }
}
