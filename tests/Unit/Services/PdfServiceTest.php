<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\PdfService;
use PHPUnit\Framework\TestCase;

final class PdfServiceTest extends TestCase
{
    public function testFromHtmlReturnsPdfBinary(): void
    {
        // Arrange
        $service = new PdfService();

        // Act
        $pdf = $service->fromHtml('<h1>Test LegalOPS</h1>');

        // Assert
        self::assertStringStartsWith('%PDF', $pdf);
    }
}
