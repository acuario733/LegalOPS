<?php

declare(strict_types=1);

namespace App\Services;

use Dompdf\Dompdf;
use RuntimeException;

final class PdfService
{
    public function fromHtml(string $html): string
    {
        if (!class_exists(Dompdf::class)) {
            throw new RuntimeException('Instale dompdf/dompdf para usar PdfService.');
        }
        $dompdf = new Dompdf(['isRemoteEnabled' => false, 'defaultFont' => 'DejaVu Sans']);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('letter');
        $dompdf->render();
        $pdf = $dompdf->output();
        if (!str_starts_with($pdf, '%PDF')) {
            throw new RuntimeException('La generacion de PDF no produjo un binario valido.');
        }

        return $pdf;
    }

    /** @param array<string, mixed> $data */
    public function fromTemplate(string $templateName, array $data): string
    {
        $template = dirname(__DIR__) . '/PdfTemplates/' . basename($templateName) . '.php';
        if (!is_file($template)) {
            throw new RuntimeException('La plantilla PDF solicitada no existe.');
        }

        ob_start();
        try {
            /** @var array<string, mixed> $data */
            require $template;
            $html = (string) ob_get_clean();
        } catch (\Throwable $exception) {
            ob_end_clean();
            throw $exception;
        }

        return $this->fromHtml($html);
    }
}
