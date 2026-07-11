<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\ContactDeduplicationService;
use PDO;
use PHPUnit\Framework\TestCase;

final class ContactDeduplicationServiceTest extends TestCase
{
    public function testFindsEmailOrDocumentMatchesOnlyInsideTenant(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE clientes (id INTEGER,firma_id INTEGER,nombre_razon_social TEXT,email TEXT,documento_hash TEXT,deleted_at TEXT)');
        $pdo->exec('CREATE TABLE prospectos (id INTEGER,firma_id INTEGER,nombre TEXT,email TEXT,documento_hash TEXT,deleted_at TEXT)');
        $hash = hash('sha256', '123');
        $pdo->exec("INSERT INTO clientes VALUES (1,1,'Cliente Uno','same@example.com',NULL,NULL),(2,2,'Otro','same@example.com',NULL,NULL)");
        $statement = $pdo->prepare("INSERT INTO prospectos VALUES (3,1,'Prospecto Tres',NULL,:hash,NULL)");
        $statement->execute(['hash' => $hash]);
        $service = new ContactDeduplicationService($pdo);

        $duplicates = $service->checkDuplicates(1, 'SAME@example.com', $hash);

        self::assertCount(2, $duplicates);
        self::assertSame(['cliente', 'prospecto'], array_column($duplicates, 'tipo'));
    }
}
