<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\DocxService;
use PHPUnit\Framework\TestCase;

final class DocxServiceTest extends TestCase
{
    public function testCreatesAnOoxmlZipPackage(): void
    {
        $docx = (new DocxService())->fromText("Contrato\n\nCliente Principal");

        self::assertStringStartsWith("PK\x03\x04", $docx);
        self::assertStringContainsString('[Content_Types].xml', $docx);
        self::assertStringContainsString('word/document.xml', $docx);
        self::assertStringEndsWith(pack('v', 0), $docx);
    }
}
