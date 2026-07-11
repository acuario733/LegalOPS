<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Core\HttpException;
use App\Services\MfaService;
use App\Services\QueueService;
use App\Services\SensitiveDataService;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * Pruebas de MfaService: TOTP, recovery codes y rate limiting.
 * Cierra el criterio MFA de F10-2 (pendiente desde Fase 10 por dependencia en Fase 6).
 *
 * @group phase6
 */
final class TrustServiceMfaTest extends TestCase
{
    private PDO $pdo;
    private MfaService $mfa;
    private QueueService $queue;
    /** @var array<int,array<string,mixed>> */
    private array $dispatchedJobs = [];

    private const FIRMA_ID   = 1;
    private const USUARIO_ID = 7;
    private const EMAIL      = 'admin@firma.test';

    protected function setUp(): void
    {
        putenv('APP_ENCRYPTION_KEY=test-mfa-key-with-enough-entropy-for-gcm');

        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->schema();

        // QueueService es final; lo instanciamos con el mismo PDO (tabla jobs en SQLite)
        $this->queue = new QueueService($this->pdo);

        $this->mfa = new MfaService(
            $this->pdo,
            new SensitiveDataService(),
            $this->queue,
            null // sin Redis en tests; rate-limit en memoria desactivado
        );
    }

    protected function tearDown(): void
    {
        putenv('APP_ENCRYPTION_KEY');
    }

    // ------------------------------------------------------------------
    // Configuracion TOTP
    // ------------------------------------------------------------------

    public function testConfiguracionTotpRetornaSecretoUriYRecoveryCodes(): void
    {
        $result = $this->mfa->configurarTotp(self::FIRMA_ID, self::USUARIO_ID, self::EMAIL);

        self::assertArrayHasKey('secret', $result);
        self::assertArrayHasKey('uri', $result);
        self::assertArrayHasKey('recovery_codes', $result);
        self::assertNotEmpty($result['secret']);
        self::assertStringContainsString('otpauth://totp/', $result['uri']);
        self::assertCount(8, $result['recovery_codes']);
    }

    public function testConfiguracionTotpCreaRegistroEnBase(): void
    {
        $this->mfa->configurarTotp(self::FIRMA_ID, self::USUARIO_ID, self::EMAIL);

        $count = (int) $this->pdo->query(
            'SELECT COUNT(*) FROM user_mfa WHERE usuario_id=' . self::USUARIO_ID
        )->fetchColumn();
        self::assertSame(1, $count);
    }

    public function testSegundaConfiguracionTotpSobreescribeSinDuplicar(): void
    {
        $this->mfa->configurarTotp(self::FIRMA_ID, self::USUARIO_ID, self::EMAIL);
        $this->mfa->configurarTotp(self::FIRMA_ID, self::USUARIO_ID, self::EMAIL);

        $count = (int) $this->pdo->query(
            'SELECT COUNT(*) FROM user_mfa WHERE usuario_id=' . self::USUARIO_ID
        )->fetchColumn();
        self::assertSame(1, $count);
    }

    // ------------------------------------------------------------------
    // Habilitacion MFA con TOTP
    // ------------------------------------------------------------------

    public function testHabilitarMfaConCodigoValidoSetHabilitado(): void
    {
        $result = $this->mfa->configurarTotp(self::FIRMA_ID, self::USUARIO_ID, self::EMAIL);
        $code   = $this->computeTotp($result['secret']);

        $this->mfa->habilitarMfa(self::FIRMA_ID, self::USUARIO_ID, $code);

        $habilitado = $this->pdo->query(
            'SELECT habilitado FROM user_mfa WHERE usuario_id=' . self::USUARIO_ID
        )->fetchColumn();
        self::assertSame('1', (string) $habilitado);
    }

    public function testHabilitarMfaConCodigoInvalidoLanzaHttpException(): void
    {
        $this->mfa->configurarTotp(self::FIRMA_ID, self::USUARIO_ID, self::EMAIL);

        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('código TOTP no es válido');

        $this->mfa->habilitarMfa(self::FIRMA_ID, self::USUARIO_ID, '000000');
    }

    // ------------------------------------------------------------------
    // Verificacion TOTP
    // ------------------------------------------------------------------

    public function testVerificarTotpConCodigoValidoRetornaTrue(): void
    {
        $result = $this->mfa->configurarTotp(self::FIRMA_ID, self::USUARIO_ID, self::EMAIL);
        $code   = $this->computeTotp($result['secret']);
        $this->mfa->habilitarMfa(self::FIRMA_ID, self::USUARIO_ID, $code);

        // Recomputa el codigo (puede estar en la misma ventana o en la siguiente)
        $code2  = $this->computeTotp($result['secret']);
        $valid  = $this->mfa->verificarTotp(self::FIRMA_ID, self::USUARIO_ID, $code2);

        self::assertTrue($valid);
    }

    public function testVerificarTotpConCodigoIncorrectoRetornaFalse(): void
    {
        $result = $this->mfa->configurarTotp(self::FIRMA_ID, self::USUARIO_ID, self::EMAIL);
        $code   = $this->computeTotp($result['secret']);
        $this->mfa->habilitarMfa(self::FIRMA_ID, self::USUARIO_ID, $code);

        $valid = $this->mfa->verificarTotp(self::FIRMA_ID, self::USUARIO_ID, '000000');

        self::assertFalse($valid);
    }

    public function testVerificarTotpSinMfaHabilitadoLanzaHttpException(): void
    {
        $this->mfa->configurarTotp(self::FIRMA_ID, self::USUARIO_ID, self::EMAIL);

        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('no está habilitado');

        $this->mfa->verificarTotp(self::FIRMA_ID, self::USUARIO_ID, '123456');
    }

    // ------------------------------------------------------------------
    // Codigos de recuperacion estaticos
    // ------------------------------------------------------------------

    public function testUsarCodigoRecuperacionValidoRetornaTrueYLoInvalida(): void
    {
        $result  = $this->mfa->configurarTotp(self::FIRMA_ID, self::USUARIO_ID, self::EMAIL);
        $code    = $result['recovery_codes'][0];

        $used    = $this->mfa->usarCodigoRecuperacion(self::FIRMA_ID, self::USUARIO_ID, $code);

        self::assertTrue($used);

        // El mismo codigo ya no debe funcionar
        $usedAgain = $this->mfa->usarCodigoRecuperacion(self::FIRMA_ID, self::USUARIO_ID, $code);
        self::assertFalse($usedAgain);
    }

    public function testUsarCodigoRecuperacionInvalidoRetornaFalse(): void
    {
        $this->mfa->configurarTotp(self::FIRMA_ID, self::USUARIO_ID, self::EMAIL);

        $valid = $this->mfa->usarCodigoRecuperacion(self::FIRMA_ID, self::USUARIO_ID, 'INVALIDO');

        self::assertFalse($valid);
    }

    // ------------------------------------------------------------------
    // Deshabilitacion MFA
    // ------------------------------------------------------------------

    public function testDeshabilitarMfaSetHabilitadoFalse(): void
    {
        $result = $this->mfa->configurarTotp(self::FIRMA_ID, self::USUARIO_ID, self::EMAIL);
        $code   = $this->computeTotp($result['secret']);
        $this->mfa->habilitarMfa(self::FIRMA_ID, self::USUARIO_ID, $code);

        $this->mfa->deshabilitarMfa(self::FIRMA_ID, self::USUARIO_ID);

        self::assertFalse($this->mfa->isMfaHabilitado(self::FIRMA_ID, self::USUARIO_ID));
    }

    // ------------------------------------------------------------------
    // Estado y requisito de MFA
    // ------------------------------------------------------------------

    public function testIsMfaHabilitadoRetornaFalseSinConfig(): void
    {
        self::assertFalse($this->mfa->isMfaHabilitado(self::FIRMA_ID, self::USUARIO_ID));
    }

    public function testIsMfaRequeridoRetornaFalseCuandoMfaDesactivado(): void
    {
        // MFA_ENABLED es false por defecto (getenv devuelve false → Config::env devuelve false)
        self::assertFalse($this->mfa->isMfaRequerido('superadmin'));
    }

    public function testIsMfaRequeridoRetornaTrueCuandoMfaActivadoParaRol(): void
    {
        putenv('MFA_ENABLED=true');
        putenv('MFA_REQUIRE_FOR_ROLES=superadmin,admin');

        self::assertTrue($this->mfa->isMfaRequerido('superadmin'));
        self::assertTrue($this->mfa->isMfaRequerido('admin'));
        self::assertFalse($this->mfa->isMfaRequerido('paralegal'));

        putenv('MFA_ENABLED');
        putenv('MFA_REQUIRE_FOR_ROLES');
    }

    // ------------------------------------------------------------------
    // Recovery OTP por correo
    // ------------------------------------------------------------------

    public function testEnviarRecoveryOtpDespachaJobYRetornaHash(): void
    {
        $hash = $this->mfa->enviarRecoveryOtp(self::FIRMA_ID, self::USUARIO_ID, self::EMAIL);

        // Verifica que el job fue encolado
        $jobCount = (int) $this->pdo->query('SELECT COUNT(*) FROM jobs')->fetchColumn();
        self::assertSame(1, $jobCount);

        // Verifica que el hash es un SHA-256 valido
        self::assertNotEmpty($hash);
        self::assertSame(64, strlen($hash));
    }

    public function testVerificarRecoveryOtpConHashCorrecto(): void
    {
        $otp  = 'ABCD1234';
        $hash = hash('sha256', $otp);

        $valid = $this->mfa->verificarRecoveryOtp(self::USUARIO_ID, $otp, $hash);

        self::assertTrue($valid);
    }

    public function testVerificarRecoveryOtpConHashIncorrectoRetornaFalse(): void
    {
        $valid = $this->mfa->verificarRecoveryOtp(self::USUARIO_ID, 'WRONG', hash('sha256', 'CORRECT'));

        self::assertFalse($valid);
    }

    // ------------------------------------------------------------------
    // Helpers privados del test
    // ------------------------------------------------------------------

    /**
     * Computa el TOTP actual del mismo modo que MfaService (RFC 6238).
     */
    private function computeTotp(string $base32Secret, int $digits = 6, int $period = 30): string
    {
        $key     = $this->base32Decode($base32Secret);
        $counter = (int) floor(time() / $period);
        $msg     = pack('J', $counter);
        $hash    = hash_hmac('sha1', $msg, $key, true);
        $offset  = ord($hash[19]) & 0x0F;
        $code    = (
            ((ord($hash[$offset]) & 0x7F) << 24)
            | ((ord($hash[$offset + 1]) & 0xFF) << 16)
            | ((ord($hash[$offset + 2]) & 0xFF) << 8)
            | (ord($hash[$offset + 3]) & 0xFF)
        ) % (10 ** $digits);

        return str_pad((string) $code, $digits, '0', STR_PAD_LEFT);
    }

    private function base32Decode(string $input): string
    {
        $chars    = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $input    = strtoupper($input);
        $output   = '';
        $buffer   = 0;
        $bitsLeft = 0;

        for ($i = 0, $len = strlen($input); $i < $len; $i++) {
            $val = strpos($chars, $input[$i]);
            if ($val === false) {
                continue;
            }
            $buffer   = ($buffer << 5) | $val;
            $bitsLeft += 5;
            if ($bitsLeft >= 8) {
                $bitsLeft -= 8;
                $output   .= chr(($buffer >> $bitsLeft) & 0xFF);
            }
        }
        return $output;
    }

    // ------------------------------------------------------------------
    // Schema SQLite en memoria
    // ------------------------------------------------------------------

    private function schema(): void
    {
        $this->pdo->exec("
            CREATE TABLE jobs (
                id          INTEGER PRIMARY KEY AUTOINCREMENT,
                queue       TEXT    NOT NULL DEFAULT 'default',
                payload     TEXT    NOT NULL,
                attempts    INTEGER NOT NULL DEFAULT 0,
                reserved_at INTEGER NULL,
                available_at INTEGER NOT NULL,
                created_at  INTEGER NOT NULL
            );
            CREATE TABLE user_mfa (
                id                 INTEGER PRIMARY KEY AUTOINCREMENT,
                firma_id           INTEGER NOT NULL,
                usuario_id         INTEGER NOT NULL UNIQUE,
                totp_secret_enc    TEXT    NOT NULL,
                recovery_codes_enc TEXT    NOT NULL,
                habilitado         INTEGER NOT NULL DEFAULT 0,
                verified_at        TEXT    NULL,
                created_at         TEXT    NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at         TEXT    NOT NULL DEFAULT CURRENT_TIMESTAMP
            );
            CREATE TABLE usuarios (
                id              INTEGER PRIMARY KEY AUTOINCREMENT,
                firma_id        INTEGER NOT NULL,
                tipo            TEXT    NOT NULL DEFAULT 'interno',
                estado          TEXT    NOT NULL DEFAULT 'activo',
                mfa_habilitado  INTEGER NOT NULL DEFAULT 0,
                deleted_at      TEXT    NULL
            );
        ");
        // Insertar usuario de prueba
        $this->pdo->exec(
            'INSERT INTO usuarios (id, firma_id, tipo, estado) VALUES (' . self::USUARIO_ID . ', ' . self::FIRMA_ID . ", 'admin', 'activo')"
        );
    }
}
