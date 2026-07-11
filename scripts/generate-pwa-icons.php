<?php

/**
 * Generador de iconos PWA para LegalOPS Cloud.
 *
 * Ejecutar una sola vez desde la raíz del proyecto:
 *   php scripts/generate-pwa-icons.php
 *
 * Requiere la extensión GD (incluida en XAMPP por defecto).
 * Si GD no está disponible, genera PNGs de color sólido como placeholders.
 */

declare(strict_types=1);

const ICON_BG    = [26, 26, 46];   // #1a1a2e
const ICON_FG    = [255, 255, 255];
const OUTPUT_DIR = __DIR__ . '/../public/assets/icons';

// ── Verificar extensión GD ────────────────────────────────────────────────────
if (!extension_loaded('gd')) {
    echo "⚠️  La extensión GD no está disponible. Se generarán placeholders mínimos.\n";
    generatePlaceholders();
    exit(0);
}

// ── Crear directorio de salida ────────────────────────────────────────────────
if (!is_dir(OUTPUT_DIR)) {
    mkdir(OUTPUT_DIR, 0755, true);
    echo "📁 Directorio creado: " . OUTPUT_DIR . "\n";
}

// ── Tamaños estándar ──────────────────────────────────────────────────────────
$sizes = [72, 96, 128, 144, 152, 192, 384, 512];

foreach ($sizes as $size) {
    $file = OUTPUT_DIR . "/icon-{$size}.png";
    generateIcon($size, $size, $file, false);
    echo "✅ icon-{$size}.png  ({$size}×{$size}px)\n";
}

// ── Maskable (padding 20%) ────────────────────────────────────────────────────
$maskableFile = OUTPUT_DIR . '/icon-512-maskable.png';
generateIcon(512, 512, $maskableFile, true);
echo "✅ icon-512-maskable.png  (512×512px, padding 20%)\n";

// ── Shortcut icons (96×96) ────────────────────────────────────────────────────
$shortcuts = [
    'shortcut-caso'      => 'C',
    'shortcut-tarea'     => 'T',
    'shortcut-prospecto' => 'P',
];

foreach ($shortcuts as $name => $letter) {
    $file = OUTPUT_DIR . "/{$name}.png";
    generateShortcutIcon(96, $file, $letter);
    echo "✅ {$name}.png  (96×96px)\n";
}

echo "\n🎉 Todos los iconos generados en: " . OUTPUT_DIR . "\n";
echo "   Total: " . (count($sizes) + 1 + count($shortcuts)) . " archivos\n\n";

// ── Funciones ─────────────────────────────────────────────────────────────────

function generateIcon(int $width, int $height, string $file, bool $maskable): void
{
    $img = imagecreatetruecolor($width, $height);
    imagealphablending($img, false);
    imagesavealpha($img, true);

    $bg = imagecolorallocate($img, ...ICON_BG);
    imagefilledrectangle($img, 0, 0, $width - 1, $height - 1, $bg);

    // Área segura para maskable: 80% del tamaño (padding 10% cada lado)
    $contentSize = $maskable ? (int) round($width * 0.8) : $width;
    $offsetX     = (int) round(($width  - $contentSize) / 2);
    $offsetY     = (int) round(($height - $contentSize) / 2);

    // "L" centrado con GD (drawLetter maneja el escalado)
    drawLetter($img, $contentSize, $offsetX, $offsetY);

    imagepng($img, $file, 9);
    imagedestroy($img);
}

function generateShortcutIcon(int $size, string $file, string $letter): void
{
    $img = imagecreatetruecolor($size, $size);
    imagealphablending($img, false);
    imagesavealpha($img, true);

    $bg = imagecolorallocate($img, ...ICON_BG);
    imagefilledrectangle($img, 0, 0, $size - 1, $size - 1, $bg);

    drawLetter($img, $size, 0, 0, $letter);

    imagepng($img, $file, 9);
    imagedestroy($img);
}

function drawLetter(
    GdImage $img,
    int $contentSize,
    int $offsetX,
    int $offsetY,
    string $letter = 'L'
): void {
    $fg    = imagecolorallocate($img, ...ICON_FG);
    $scale = max(1, (int) round($contentSize / 3));

    // GD built-in fonts: 1-5. Font 5 = 9×15px. Scale via imagestring.
    // Para iconos grandes usamos un rectángulo estilizado como placeholder de "L".
    $fontW  = 9 * 5;  // font 5 width per char ≈ 9px (not exact, but close)
    $fontH  = 15 * 5; // rough

    // Usar imagettftext si hay fuente disponible, si no: dibujamos la "L" con rectángulos
    $fontFile = __DIR__ . '/DejaVuSans-Bold.ttf';

    if (function_exists('imagettftext') && is_file($fontFile)) {
        $fontSize = (int) round($contentSize * 0.55);
        $bbox     = imagettfbbox($fontSize, 0, $fontFile, $letter);
        $textW    = abs($bbox[4] - $bbox[0]);
        $textH    = abs($bbox[5] - $bbox[1]);
        $x        = $offsetX + (int) round(($contentSize - $textW) / 2);
        $y        = $offsetY + (int) round(($contentSize + $textH) / 2);
        imagettftext($img, $fontSize, 0, $x, $y, $fg, $fontFile, $letter);
    } else {
        // Fallback: dibuja la "L" como dos rectángulos
        $strokeW = max(4, (int) round($contentSize * 0.12));
        $pad     = max(8, (int) round($contentSize * 0.18));

        // Trazo vertical
        imagefilledrectangle(
            $img,
            $offsetX + $pad,
            $offsetY + $pad,
            $offsetX + $pad + $strokeW,
            $offsetY + $contentSize - $pad,
            $fg
        );
        // Trazo horizontal
        imagefilledrectangle(
            $img,
            $offsetX + $pad,
            $offsetY + $contentSize - $pad - $strokeW,
            $offsetX + $contentSize - $pad,
            $offsetY + $contentSize - $pad,
            $fg
        );
    }
}

/**
 * Fallback sin GD: escribe PNGs mínimos de 1×1 pixel.
 * Suficiente para que el manifest no rompa, pero sin visuales.
 */
function generatePlaceholders(): void
{
    if (!is_dir(OUTPUT_DIR)) {
        mkdir(OUTPUT_DIR, 0755, true);
    }

    // PNG de 1×1 píxel azul oscuro (mínimo válido)
    $png1x1 = base64_decode(
        'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk' .
        'YPhfDwAChwGA60e6kgAAAABJRU5ErkJggg=='
    );

    $files = ['72','96','128','144','152','192','384','512'];
    foreach ($files as $s) {
        $path = OUTPUT_DIR . "/icon-{$s}.png";
        file_put_contents($path, $png1x1);
        echo "⚠️  Placeholder: icon-{$s}.png (1×1px — instalar GD para iconos reales)\n";
    }

    file_put_contents(OUTPUT_DIR . '/icon-512-maskable.png', $png1x1);
    file_put_contents(OUTPUT_DIR . '/shortcut-caso.png',      $png1x1);
    file_put_contents(OUTPUT_DIR . '/shortcut-tarea.png',     $png1x1);
    file_put_contents(OUTPUT_DIR . '/shortcut-prospecto.png', $png1x1);
    echo "⚠️  Placeholders generados. Instala la extensión GD y vuelve a ejecutar.\n";
}
