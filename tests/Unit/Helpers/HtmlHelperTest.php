<?php

declare(strict_types=1);

namespace Tests\Unit\Helpers;

use App\Helpers\HtmlHelper;
use PHPUnit\Framework\TestCase;

/**
 * Tests de seguridad para HtmlHelper.
 *
 * Verifican que los métodos de escaping bloqueen correctamente los vectores
 * XSS más comunes antes de que lleguen a las vistas PHP del sistema.
 */
class HtmlHelperTest extends TestCase
{
    // ─────────────────────────────────────────────────────────────────────────
    // h() — escaping de contenido HTML
    // ─────────────────────────────────────────────────────────────────────────

    public function test_h_escapes_script_tags(): void
    {
        $input    = '<script>alert("xss")</script>';
        $expected = '&lt;script&gt;alert(&quot;xss&quot;)&lt;/script&gt;';

        $this->assertSame($expected, HtmlHelper::h($input));
    }

    public function test_h_escapes_event_handlers(): void
    {
        // Inyección común via atributo de evento
        $inputs = [
            '<img src=x onerror=alert(1)>',
            '<a onclick="alert(1)">click</a>',
            '<body onload=alert(1)>',
            '<svg onload=alert(1)>',
        ];

        foreach ($inputs as $input) {
            $output = HtmlHelper::h($input);
            $this->assertStringNotContainsString('<', $output, "h() debe escapar '<' en: $input");
            $this->assertStringNotContainsString('>', $output, "h() debe escapar '>' en: $input");
        }
    }

    public function test_h_preserves_normal_text(): void
    {
        $inputs = [
            'García & Asociados'    => 'García &amp; Asociados',
            'Empresa "Acme" S.A.'   => 'Empresa &quot;Acme&quot; S.A.',
            'Calle O\'Higgins 123'  => 'Calle O&#039;Higgins 123',
            'Texto normal sin HTML' => 'Texto normal sin HTML',
        ];

        foreach ($inputs as $input => $expected) {
            $this->assertSame($expected, HtmlHelper::h($input));
        }
    }

    public function test_h_handles_null_and_empty(): void
    {
        $this->assertSame('', HtmlHelper::h(null));
        $this->assertSame('', HtmlHelper::h(''));
        $this->assertSame('0', HtmlHelper::h(0));
        $this->assertSame('false', HtmlHelper::h(false)); // bool → string
    }

    // ─────────────────────────────────────────────────────────────────────────
    // attr() — escaping de atributos HTML
    // ─────────────────────────────────────────────────────────────────────────

    public function test_attr_escapes_quotes_in_attributes(): void
    {
        // Un atacante intenta "romper" el atributo value para inyectar un nuevo atributo
        $input    = '" onmouseover="alert(1)';
        $output   = HtmlHelper::attr($input);

        $this->assertStringNotContainsString('"', $output, 'attr() debe escapar las comillas dobles');
        $this->assertStringContainsString('&quot;', $output);
    }

    public function test_attr_escapes_single_quotes(): void
    {
        $input  = "' onmouseover='alert(1)";
        $output = HtmlHelper::attr($input);

        $this->assertStringNotContainsString("'", $output, 'attr() debe escapar las comillas simples');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // js() — escaping para bloques JavaScript
    // ─────────────────────────────────────────────────────────────────────────

    public function test_js_encodes_strings_as_json(): void
    {
        $output = HtmlHelper::js('García & Abogados');

        // Debe ser JSON válido (con comillas)
        $this->assertJson($output);
        $this->assertSame('"García & Abogados"', $output);
    }

    public function test_js_escapes_html_tags_in_json(): void
    {
        // Previene </script> que podría cerrar el bloque JS prematuramente
        $output = HtmlHelper::js('</script><script>alert(1)</script>');

        $this->assertStringNotContainsString('</script>', $output);
        $this->assertStringNotContainsString('<script>', $output);
    }

    public function test_js_handles_arrays_and_objects(): void
    {
        $data   = ['firma_id' => 1, 'nombre' => 'García & Asoc.'];
        $output = HtmlHelper::js($data);

        $decoded = json_decode($output, true);
        $this->assertIsArray($decoded);
        $this->assertSame(1, $decoded['firma_id']);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // url() — validación y escaping de URLs
    // ─────────────────────────────────────────────────────────────────────────

    public function test_url_blocks_javascript_protocol(): void
    {
        $vectors = [
            'javascript:alert(1)',
            'JavaScript:alert(1)',
            'JAVASCRIPT:alert(1)',
            "  javascript:alert(1)",  // con espacios
            "\x00javascript:alert(1)", // con null byte
        ];

        foreach ($vectors as $vector) {
            $this->assertSame('#', HtmlHelper::url($vector), "url() debe bloquear: $vector");
        }
    }

    public function test_url_allows_valid_relative_and_absolute_urls(): void
    {
        $valid = [
            '/clientes/1'               => '/clientes/1',
            '/casos?estado=activo'      => '/casos?estado=activo',
            'https://example.com/path'  => 'https://example.com/path',
        ];

        foreach ($valid as $input => $expected) {
            $this->assertSame($expected, HtmlHelper::url($input), "url() debe permitir: $input");
        }
    }

    public function test_url_returns_hash_for_empty_values(): void
    {
        $this->assertSame('#', HtmlHelper::url(null));
        $this->assertSame('#', HtmlHelper::url(''));
    }
}
