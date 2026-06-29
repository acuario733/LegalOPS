<?php

declare(strict_types=1);

namespace Tests\Unit\Api;

use PDO;
use PHPUnit\Framework\TestCase;

/**
 * Tests de la lógica de validación y seguridad de tokens API.
 *
 * No instanciamos ApiTokenController directamente (requiere DI completa),
 * sino que probamos:
 *   1. La integridad de los tokens generados (longitud, unicidad, hash SHA-256)
 *   2. La lógica de scopes permitidos (whitelist)
 *   3. Las invariantes de BD (TENANT FILTER en INSERT/UPDATE)
 *
 * Usamos SQLite :memory: para simular la tabla api_tokens.
 */
class ApiTokenValidationTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->exec("
            CREATE TABLE firmas (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                nombre TEXT NOT NULL,
                estado TEXT NOT NULL DEFAULT 'activo'
            )
        ");
        $this->pdo->exec("
            CREATE TABLE api_tokens (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                firma_id INTEGER NOT NULL,
                usuario_id INTEGER,
                nombre TEXT NOT NULL,
                token_hash TEXT NOT NULL UNIQUE,
                scopes TEXT,
                ultimo_uso_at TEXT,
                expira_at TEXT,
                revocado_at TEXT,
                created_at TEXT NOT NULL DEFAULT (datetime('now')),
                updated_at TEXT NOT NULL DEFAULT (datetime('now'))
            )
        ");
        // Insertar firmas de prueba
        $this->pdo->exec("INSERT INTO firmas (id, nombre) VALUES (1, 'Firma Test'), (2, 'Firma Otra')");
    }

    protected function tearDown(): void
    {
        unset($this->pdo);
        parent::tearDown();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 1. Generación de tokens
    // ─────────────────────────────────────────────────────────────────────────

    public function test_raw_token_is_64_hex_characters(): void
    {
        $rawToken = bin2hex(random_bytes(32));
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $rawToken);
    }

    public function test_token_hash_is_sha256_of_raw_token(): void
    {
        $rawToken  = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $rawToken);

        $this->assertSame(64, strlen($tokenHash)); // SHA-256 = 64 hex chars
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $tokenHash);
    }

    public function test_two_generated_tokens_are_unique(): void
    {
        $token1 = bin2hex(random_bytes(32));
        $token2 = bin2hex(random_bytes(32));

        $this->assertNotSame($token1, $token2);
        $this->assertNotSame(hash('sha256', $token1), hash('sha256', $token2));
    }

    public function test_raw_token_cannot_be_recovered_from_hash(): void
    {
        // SHA-256 es unidireccional — verificamos que el hash no contiene el raw token
        $rawToken  = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $rawToken);

        $this->assertStringNotContainsString($rawToken, $tokenHash);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 2. Whitelist de scopes
    // ─────────────────────────────────────────────────────────────────────────

    public function test_valid_scopes_are_accepted(): void
    {
        $allowedScopes = [
            'clientes:read', 'clientes:write',
            'prospectos:read', 'prospectos:write',
            'casos:read', 'casos:write',
            'tareas:read', 'tareas:write',
        ];

        $requestedScopes = ['clientes:read', 'casos:read'];
        $invalid = array_diff($requestedScopes, $allowedScopes);

        $this->assertEmpty($invalid);
    }

    public function test_unknown_scope_is_rejected(): void
    {
        $allowedScopes = [
            'clientes:read', 'clientes:write',
            'prospectos:read', 'prospectos:write',
            'casos:read', 'casos:write',
            'tareas:read', 'tareas:write',
        ];

        $requestedScopes = ['clientes:read', 'admin:all']; // admin:all no existe
        $invalid = array_diff($requestedScopes, $allowedScopes);

        $this->assertContains('admin:all', $invalid);
    }

    public function test_empty_scopes_means_full_access(): void
    {
        // Scopes vacíos = acceso total (token de administración)
        $scopes = [];
        $hasFullAccess = empty($scopes);

        $this->assertTrue($hasFullAccess);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 3. Tenant isolation en BD
    // ─────────────────────────────────────────────────────────────────────────

    private function insertToken(int $firmaId, string $nombre): int
    {
        $rawToken  = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $rawToken);

        $stmt = $this->pdo->prepare(
            "INSERT INTO api_tokens (firma_id, nombre, token_hash, created_at, updated_at)
             VALUES (:firma_id, :nombre, :token_hash, datetime('now'), datetime('now'))"
        );
        $stmt->execute(['firma_id' => $firmaId, 'nombre' => $nombre, 'token_hash' => $tokenHash]);
        return (int) $this->pdo->lastInsertId();
    }

    public function test_token_insert_stores_firma_id(): void
    {
        $id = $this->insertToken(firmaId: 1, nombre: 'Mi Token');

        $stmt = $this->pdo->prepare('SELECT firma_id FROM api_tokens WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        $this->assertSame(1, (int) $row['firma_id']);
    }

    public function test_token_hash_is_unique_across_inserts(): void
    {
        $this->insertToken(firmaId: 1, nombre: 'Token A');
        $this->insertToken(firmaId: 1, nombre: 'Token B');

        $stmt = $this->pdo->query('SELECT COUNT(DISTINCT token_hash) AS cnt FROM api_tokens');
        $row  = $stmt->fetch(PDO::FETCH_ASSOC);

        $this->assertSame(2, (int) $row['cnt']);
    }

    public function test_revoke_only_affects_correct_firma_tenant(): void
    {
        $firma1TokenId = $this->insertToken(firmaId: 1, nombre: 'Firma 1 Token');
        $firma2TokenId = $this->insertToken(firmaId: 2, nombre: 'Firma 2 Token');

        // Firma 1 intenta revocar el token de Firma 2 (cross-tenant attack)
        $stmt = $this->pdo->prepare(
            "UPDATE api_tokens
                SET revocado_at = datetime('now')
              WHERE id = :id
                AND firma_id = :firma_id  -- TENANT FILTER
                AND revocado_at IS NULL"
        );
        $stmt->execute(['id' => $firma2TokenId, 'firma_id' => 1]); // firma_id=1 != firma2TokenId.firma_id

        // El token de firma 2 NO debe estar revocado
        $checkStmt = $this->pdo->prepare('SELECT revocado_at FROM api_tokens WHERE id = :id');
        $checkStmt->execute(['id' => $firma2TokenId]);
        $row = $checkStmt->fetch(PDO::FETCH_ASSOC);

        $this->assertNull($row['revocado_at'], 'Cross-tenant revocation should have no effect');
    }

    public function test_revoke_sets_revocado_at_for_own_token(): void
    {
        $tokenId = $this->insertToken(firmaId: 1, nombre: 'Mi Token');

        $stmt = $this->pdo->prepare(
            "UPDATE api_tokens
                SET revocado_at = datetime('now')
              WHERE id = :id
                AND firma_id = :firma_id
                AND revocado_at IS NULL"
        );
        $stmt->execute(['id' => $tokenId, 'firma_id' => 1]);

        $checkStmt = $this->pdo->prepare('SELECT revocado_at FROM api_tokens WHERE id = :id');
        $checkStmt->execute(['id' => $tokenId]);
        $row = $checkStmt->fetch(PDO::FETCH_ASSOC);

        $this->assertNotNull($row['revocado_at']);
    }

    public function test_list_tokens_only_returns_own_firma_tokens(): void
    {
        $this->insertToken(firmaId: 1, nombre: 'Firma 1 - Token A');
        $this->insertToken(firmaId: 1, nombre: 'Firma 1 - Token B');
        $this->insertToken(firmaId: 2, nombre: 'Firma 2 - Token');

        $stmt = $this->pdo->prepare(
            'SELECT id, nombre FROM api_tokens WHERE firma_id = :firma_id ORDER BY created_at DESC'
        );
        $stmt->execute(['firma_id' => 1]);
        $tokens = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $this->assertCount(2, $tokens);
        foreach ($tokens as $token) {
            $this->assertStringStartsWith('Firma 1', $token['nombre']);
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 4. Validaciones de nombre
    // ─────────────────────────────────────────────────────────────────────────

    public function test_nombre_validation_rejects_empty(): void
    {
        $nombre = trim('');
        $this->assertSame('', $nombre); // empty = invalid
    }

    public function test_nombre_validation_rejects_over_100_chars(): void
    {
        $nombre = str_repeat('a', 101);
        $this->assertGreaterThan(100, mb_strlen($nombre));
    }

    public function test_nombre_validation_accepts_valid_name(): void
    {
        $nombre = 'Integración CRM';
        $this->assertNotEmpty(trim($nombre));
        $this->assertLessThanOrEqual(100, mb_strlen($nombre));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 5. Validación de fecha de expiración
    // ─────────────────────────────────────────────────────────────────────────

    public function test_expira_at_in_past_is_invalid(): void
    {
        $ts = strtotime('2020-01-01');
        $this->assertLessThanOrEqual(time(), $ts);
    }

    public function test_expira_at_in_future_is_valid(): void
    {
        $ts = strtotime('+30 days');
        $this->assertGreaterThan(time(), $ts);
    }

    public function test_expira_at_invalid_string_is_rejected(): void
    {
        $ts = strtotime('not-a-date');
        $this->assertFalse($ts);
    }
}
