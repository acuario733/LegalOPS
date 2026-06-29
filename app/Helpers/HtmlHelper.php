<?php

declare(strict_types=1);

namespace App\Helpers;

/**
 * Helper de escaping XSS para vistas PHP de LegalOPS Cloud V2.
 *
 * POLÍTICA DE ESCAPING — Usar la función correcta según el contexto:
 *
 *   h($val)    → Dentro de tags HTML:   <td><?= h($cliente['nombre']) ?></td>
 *   attr($val) → En atributos HTML:     value="<?= attr($cliente['nombre']) ?>"
 *   attr($val) → En atributos data-*:   data-id="<?= attr($cliente['id']) ?>"
 *   js($val)   → En bloques <script>:   const name = <?= js($cliente['nombre']) ?>;
 *   url($val)  → En URLs (href, src):   href="<?= url($path) ?>"
 *
 * LOS CONTEXTOS MÁS PELIGROSOS EN ESTE SISTEMA:
 *   1. Inputs de formulario (value="...") → usar attr()
 *   2. Atributos data-* con IDs/emails   → usar attr()
 *   3. Datos del cliente en tablas        → usar h()
 *   4. Descripciones de caso en textarea  → usar h()
 *   5. JSON embebido en <script>          → usar js()
 */
final class HtmlHelper
{
    /**
     * Escapa para contexto HTML (entre etiquetas).
     *
     * Uso: <td><?= h($cliente['nombre_razon_social']) ?></td>
     */
    public static function h(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        return htmlspecialchars(
            (string) $value,
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8'
        );
    }

    /**
     * Escapa para contexto de atributo HTML (value="...", data-*="...").
     *
     * Uso: <input value="<?= attr($cliente['email']) ?>">
     *      <tr data-id="<?= attr($cliente['id']) ?>">
     */
    public static function attr(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        return htmlspecialchars(
            (string) $value,
            ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5,
            'UTF-8'
        );
    }

    /**
     * Escapa para embeber datos PHP en bloques JavaScript.
     *
     * Uso: <script>const nombre = <?= js($cliente['nombre']) ?>;</script>
     *      <script>const config = <?= js(['key' => 'value']) ?>;</script>
     *
     * Retorna JSON con caracteres peligrosos adicionales escapados.
     */
    public static function js(mixed $value): string
    {
        $json = json_encode($value, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE);

        if ($json === false) {
            return 'null';
        }

        return $json;
    }

    /**
     * Escapa para uso en URLs (href, src, action).
     *
     * Valida que la URL no use protocolos peligrosos (javascript:, data:, vbscript:).
     * Retorna '#' si la URL es peligrosa o inválida.
     *
     * Uso: <a href="<?= url($enlace) ?>">Ver</a>
     *      <form action="<?= url($accion) ?>">
     */
    public static function url(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '#';
        }

        $url = trim((string) $value);

        // Detectar protocolos peligrosos (case-insensitive, con posibles espacios/tabs)
        $stripped = strtolower(preg_replace('/[\s\x00-\x1f]/u', '', $url) ?? '');
        foreach (['javascript:', 'data:', 'vbscript:', 'blob:'] as $dangerous) {
            if (str_starts_with($stripped, $dangerous)) {
                return '#';
            }
        }

        return htmlspecialchars($url, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

// ─── Funciones globales de conveniencia (para uso en vistas sin namespace) ───
// Incluidas via autoload o require en el layout principal.

if (!function_exists('h')) {
    /**
     * @see HtmlHelper::h()
     */
    function h(mixed $value): string
    {
        return \App\Helpers\HtmlHelper::h($value);
    }
}

if (!function_exists('attr')) {
    /**
     * @see HtmlHelper::attr()
     */
    function attr(mixed $value): string
    {
        return \App\Helpers\HtmlHelper::attr($value);
    }
}

if (!function_exists('js')) {
    /**
     * @see HtmlHelper::js()
     */
    function js(mixed $value): string
    {
        return \App\Helpers\HtmlHelper::js($value);
    }
}

if (!function_exists('url_safe')) {
    /**
     * @see HtmlHelper::url()
     * Nombre url_safe para evitar colisión con la función url() de PHP
     */
    function url_safe(mixed $value): string
    {
        return \App\Helpers\HtmlHelper::url($value);
    }
}
