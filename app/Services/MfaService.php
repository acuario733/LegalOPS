<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Core\HttpException;
use PDO;
use Predis\Client as RedisClient;
use RuntimeException;

/**
 * MFA con TOTP (RFC 6238) y codigos de recuperacion de un solo uso.
 *
 * Secreto TOTP y codigos de recuperacion se cifran con SensitiveDataService
 * usando MFA_ENCRYPTION_KEY o APP_KEY como fallback.
 *
 * Redis almacena intentos fallidos bajo mfa:attempts:{usuarioId} con TTL configurable.
 */
final class MfaService
{
    private const REDIS_PREFIX = 'mfa:attempts:';
    private const TOTP_PERIOD  = 30;
    private const BASE32_CHARS = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    public function __construct(
        private readonly PDO $pdo,
        private readonly SensitiveDataService $sensitive,
        private readonly QueueService $queue,
        private readonly ?RedisClient $redis = null
    ) {
    }

    // ------------------------------------------------------------------
    // Setup
    // ------------------------------------------------------------------

    /**
     * Genera y persiste un nuevo secreto TOTP para el usuario.
     * Retorna ['secret' => base32, 'uri' => otpauth_uri, 'recovery_codes' => string[]].
     * El MFA no queda habilitado hasta llamar a habilitarMfa().
     *
     * @return array{secret:string,uri:string,recovery_codes:string[]}
     */
    public function configurarTotp(int $firmaId, int $usuarioId, string $email): array
    {
        $secret        = $this->generateBase32Secret(20);
        $recoveryCodes = $this->generateRecoveryCodes();

        $secretEnc   = $this->sensitiveEncrypt($secret);
        $recoveryEnc = $this->sensitiveEncrypt(json_encode($recoveryCodes, JSON_THROW_ON_ERROR));

        $existente = $this->findMfa($firmaId, $usuarioId);
        if ($existente !== null) {
            $stmt = $this->pdo->prepare(
                'UPDATE user_mfa
                    SET totp_secret_enc=:s, recovery_codes_enc=:r, habilitado=0, verified_at=NULL, updated_at=CURRENT_TIMESTAMP
                  WHERE id=:id AND firma_id=:firma_id'
            );
            $stmt->execute(['s' => $secretEnc, 'r' => $recoveryEnc, 'id' => (int) $existente['id'], 'firma_id' => $firmaId]);
        } else {
            $stmt = $this->pdo->prepare(
                'INSERT INTO user_mfa (firma_id, usuario_id, totp_secret_enc, recovery_codes_enc, habilitado)
                 VALUES (:firma_id, :usuario_id, :s, :r, 0)'
            );
            $stmt->execute(['firma_id' => $firmaId, 'usuario_id' => $usuarioId, 's' => $secretEnc, 'r' => $recoveryEnc]);
        }

        $issuer = (string) Config::get('mfa.issuer', 'LegalOPS Cloud');
        $digits = (int) Config::get('mfa.digits', 6);
        $uri    = sprintf(
            'otpauth://totp/%s:%s?secret=%s&issuer=%s&digits=%d&period=%d',
            rawurlencode($issuer),
            rawurlencode($email),
            $secret,
            rawurlencode($issuer),
            $digits,
            self::TOTP_PERIOD
        );

        return ['secret' => $secret, 'uri' => $uri, 'recovery_codes' => $recoveryCodes];
    }

    /**
     * Habilita MFA tras verificar por primera vez el TOTP generado.
     */
    public function habilitarMfa(int $firmaId, int $usuarioId, string $codigoTotp): void
    {
        $mfa = $this->findMfaOrFail($firmaId, $usuarioId);
        $secret = $this->sensitiveDecrypt((string) $mfa['totp_secret_enc']);

        if (!$this->checkTotp($secret, $codigoTotp)) {
            throw new HttpException(422, 'El código TOTP no es válido. Verifique su aplicación autenticadora.');
        }

        $this->pdo->prepare(
            'UPDATE user_mfa
                SET habilitado=1, verified_at=CURRENT_TIMESTAMP, updated_at=CURRENT_TIMESTAMP
              WHERE id=:id AND firma_id=:firma_id'
        )->execute(['id' => (int) $mfa['id'], 'firma_id' => $firmaId]);

        $this->pdo->prepare(
            'UPDATE usuarios SET mfa_habilitado=1 WHERE id=:uid AND firma_id=:firma_id'
        )->execute(['uid' => $usuarioId, 'firma_id' => $firmaId]);
    }

    public function deshabilitarMfa(int $firmaId, int $usuarioId): void
    {
        $this->pdo->prepare(
            'UPDATE user_mfa SET habilitado=0, updated_at=CURRENT_TIMESTAMP
              WHERE usuario_id=:uid AND firma_id=:firma_id'
        )->execute(['uid' => $usuarioId, 'firma_id' => $firmaId]);

        $this->pdo->prepare(
            'UPDATE usuarios SET mfa_habilitado=0 WHERE id=:uid AND firma_id=:firma_id'
        )->execute(['uid' => $usuarioId, 'firma_id' => $firmaId]);

        $this->clearAttempts($usuarioId);
    }

    // ------------------------------------------------------------------
    // Verificacion TOTP
    // ------------------------------------------------------------------

    public function verificarTotp(int $firmaId, int $usuarioId, string $codigo): bool
    {
        $this->checkAttemptsLimit($usuarioId);
        $mfa = $this->findMfaOrFail($firmaId, $usuarioId);

        if (!(bool) $mfa['habilitado']) {
            throw new HttpException(422, 'MFA no está habilitado para este usuario.');
        }

        $secret = $this->sensitiveDecrypt((string) $mfa['totp_secret_enc']);
        $valid  = $this->checkTotp($secret, $codigo);

        if (!$valid) {
            $this->incrementAttempts($usuarioId);
            return false;
        }

        $this->clearAttempts($usuarioId);
        return true;
    }

    // ------------------------------------------------------------------
    // Recovery OTP
    // ------------------------------------------------------------------

    /**
     * Genera un OTP de recuperacion temporal, lo envia por correo y lo guarda
     * hasheado en sesion/Redis para verificacion posterior.
     * El envio se despacha a la cola MFA_RECOVERY_QUEUE.
     */
    public function enviarRecoveryOtp(int $firmaId, int $usuarioId, string $email): string
    {
        $length = (int) Config::get('mfa.recovery_length', 8);
        $otp    = strtoupper(bin2hex(random_bytes((int) ceil($length / 2))));
        $otp    = substr($otp, 0, $length);
        $hash   = hash('sha256', $otp);
        $queue  = (string) Config::get('mfa.recovery_queue', 'mfa.recovery_otp.send');
        $ttl    = (int) Config::get('mfa.attempts_ttl', 900);

        // Persiste hash en Redis con TTL
        if ($this->redis !== null) {
            $key = 'mfa:recovery_otp:' . $usuarioId;
            $this->redis->setex($key, $ttl, $hash);
        }

        $this->queue->dispatch(
            \App\Jobs\SendMfaRecoveryOtpJob::class,
            ['firma_id' => $firmaId, 'usuario_id' => $usuarioId, 'email' => $email, 'otp' => $otp],
            $queue
        );

        return $hash;
    }

    /**
     * Verifica un OTP de recuperacion enviado por correo.
     * El hash esperado proviene de enviarRecoveryOtp() y debería estar en sesion.
     */
    public function verificarRecoveryOtp(int $usuarioId, string $otp, string $hashEsperado): bool
    {
        $this->checkAttemptsLimit($usuarioId);

        $hashRecibido = hash('sha256', strtoupper(trim($otp)));
        $valid = hash_equals($hashEsperado, $hashRecibido);

        if (!$valid) {
            // Verificar tambien contra Redis si el hash no coincide con el de sesion
            if ($this->redis !== null) {
                $stored = (string) ($this->redis->get('mfa:recovery_otp:' . $usuarioId) ?? '');
                $valid  = $stored !== '' && hash_equals($stored, $hashRecibido);
                if ($valid) {
                    $this->redis->del(['mfa:recovery_otp:' . $usuarioId]);
                }
            }
        }

        if (!$valid) {
            $this->incrementAttempts($usuarioId);
            return false;
        }

        $this->clearAttempts($usuarioId);
        return true;
    }

    /**
     * Valida uno de los codigos de recuperacion estaticos y lo invalida tras el uso.
     *
     * @return bool true si un codigo valido fue encontrado y consumido
     */
    public function usarCodigoRecuperacion(int $firmaId, int $usuarioId, string $codigo): bool
    {
        $this->checkAttemptsLimit($usuarioId);
        $mfa = $this->findMfaOrFail($firmaId, $usuarioId);

        $codes = json_decode(
            $this->sensitiveDecrypt((string) $mfa['recovery_codes_enc']),
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        if (!is_array($codes)) {
            return false;
        }

        $codigoNorm = strtoupper(trim($codigo));
        $found = false;
        $remaining = [];

        foreach ($codes as $stored) {
            if (!$found && hash_equals((string) $stored, $codigoNorm)) {
                $found = true;
                continue;
            }
            $remaining[] = $stored;
        }

        if (!$found) {
            $this->incrementAttempts($usuarioId);
            return false;
        }

        // Actualiza la lista sin el codigo consumido
        $newEnc = $this->sensitiveEncrypt(json_encode($remaining, JSON_THROW_ON_ERROR));
        $this->pdo->prepare(
            'UPDATE user_mfa SET recovery_codes_enc=:r, updated_at=CURRENT_TIMESTAMP
              WHERE id=:id AND firma_id=:firma_id'
        )->execute(['r' => $newEnc, 'id' => (int) $mfa['id'], 'firma_id' => $firmaId]);

        $this->clearAttempts($usuarioId);
        return true;
    }

    // ------------------------------------------------------------------
    // Consultas de estado
    // ------------------------------------------------------------------

    public function isMfaHabilitado(int $firmaId, int $usuarioId): bool
    {
        $mfa = $this->findMfa($firmaId, $usuarioId);
        return $mfa !== null && (bool) $mfa['habilitado'];
    }

    /**
     * Determina si el rol del usuario requiere MFA segun configuracion.
     * Lee directamente de env para funcionar tanto con Config cargado como en tests unitarios.
     */
    public function isMfaRequerido(string $tipo): bool
    {
        $enabled = Config::get('mfa.enabled', null);
        if ($enabled === null) {
            $enabled = (bool) Config::env('MFA_ENABLED', false);
        }
        if (!(bool) $enabled) {
            return false;
        }

        $roles = Config::get('mfa.require_for_roles', null);
        if ($roles === null) {
            $rawRoles = (string) Config::env('MFA_REQUIRE_FOR_ROLES', 'superadmin,admin');
            $roles    = array_filter(explode(',', $rawRoles));
        }
        return in_array($tipo, (array) $roles, true);
    }

    // ------------------------------------------------------------------
    // Rate limiting de intentos MFA
    // ------------------------------------------------------------------

    private function checkAttemptsLimit(int $usuarioId): void
    {
        $max = (int) Config::get('mfa.attempts_max', 5);
        if ($this->getAttempts($usuarioId) >= $max) {
            throw new HttpException(429, 'Demasiados intentos de verificación MFA. Espere antes de reintentar.');
        }
    }

    private function getAttempts(int $usuarioId): int
    {
        if ($this->redis === null) {
            return 0; // sin Redis no hay bloqueo persistente en dev
        }
        $val = $this->redis->get(self::REDIS_PREFIX . $usuarioId);
        return is_string($val) ? (int) $val : 0;
    }

    private function incrementAttempts(int $usuarioId): void
    {
        if ($this->redis === null) {
            return;
        }
        $key = self::REDIS_PREFIX . $usuarioId;
        $ttl = (int) Config::get('mfa.attempts_ttl', 900);
        $this->redis->multi();
        $this->redis->incr($key);
        $this->redis->expire($key, $ttl);
        $this->redis->exec();
    }

    private function clearAttempts(int $usuarioId): void
    {
        if ($this->redis !== null) {
            $this->redis->del([self::REDIS_PREFIX . $usuarioId]);
        }
    }

    // ------------------------------------------------------------------
    // TOTP (RFC 6238 / RFC 4226)
    // ------------------------------------------------------------------

    private function checkTotp(string $base32Secret, string $code): bool
    {
        $digits  = (int) Config::get('mfa.digits', 6);
        $window  = (int) Config::get('mfa.window', 1);
        $counter = (int) floor(time() / self::TOTP_PERIOD);
        $codeNorm = preg_replace('/\s+/', '', $code) ?? '';

        for ($offset = -$window; $offset <= $window; $offset++) {
            if (hash_equals($this->hotp($base32Secret, $counter + $offset, $digits), $codeNorm)) {
                return true;
            }
        }
        return false;
    }

    private function hotp(string $base32Secret, int $counter, int $digits): string
    {
        $key  = $this->base32Decode($base32Secret);
        $msg  = pack('J', $counter); // big-endian uint64
        $hash = hash_hmac('sha1', $msg, $key, true);
        $offset = ord($hash[19]) & 0x0F;
        $code = (
            ((ord($hash[$offset]) & 0x7F) << 24)
            | ((ord($hash[$offset + 1]) & 0xFF) << 16)
            | ((ord($hash[$offset + 2]) & 0xFF) << 8)
            | (ord($hash[$offset + 3]) & 0xFF)
        ) % (10 ** $digits);

        return str_pad((string) $code, $digits, '0', STR_PAD_LEFT);
    }

    private function base32Decode(string $input): string
    {
        $input   = strtoupper($input);
        $output  = '';
        $buffer  = 0;
        $bitsLeft = 0;

        for ($i = 0, $len = strlen($input); $i < $len; $i++) {
            $val = strpos(self::BASE32_CHARS, $input[$i]);
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

    private function generateBase32Secret(int $bytes): string
    {
        $random = random_bytes($bytes);
        $output = '';
        $buffer = 0;
        $bitsLeft = 0;

        foreach (str_split($random) as $char) {
            $buffer   = ($buffer << 8) | ord($char);
            $bitsLeft += 8;
            while ($bitsLeft >= 5) {
                $bitsLeft -= 5;
                $output   .= self::BASE32_CHARS[($buffer >> $bitsLeft) & 0x1F];
            }
        }
        if ($bitsLeft > 0) {
            $output .= self::BASE32_CHARS[($buffer << (5 - $bitsLeft)) & 0x1F];
        }
        return $output;
    }

    /** @return string[] */
    private function generateRecoveryCodes(): array
    {
        $count  = (int) Config::get('mfa.recovery_count', 8);
        $length = (int) Config::get('mfa.recovery_length', 8);
        $codes  = [];
        for ($i = 0; $i < $count; $i++) {
            $codes[] = strtoupper(bin2hex(random_bytes((int) ceil($length / 2)))) . substr('', 0, $length);
            $codes[$i] = strtoupper(substr(bin2hex(random_bytes($length)), 0, $length));
        }
        return $codes;
    }

    // ------------------------------------------------------------------
    // Cifrado TOTP con clave dedicada o APP_KEY
    // ------------------------------------------------------------------

    private function sensitiveEncrypt(string $value): string
    {
        $dedicatedKey = (string) Config::get('mfa.encryption_key', '');
        if ($dedicatedKey !== '') {
            return $this->encryptWithKey($value, $dedicatedKey);
        }
        return $this->sensitive->encrypt($value);
    }

    private function sensitiveDecrypt(string $payload): string
    {
        $dedicatedKey = (string) Config::get('mfa.encryption_key', '');
        if ($dedicatedKey !== '') {
            return $this->decryptWithKey($payload, $dedicatedKey);
        }
        return $this->sensitive->decrypt($payload);
    }

    private function encryptWithKey(string $value, string $keyRaw): string
    {
        $key = substr(hash('sha256', $keyRaw, true), 0, 32);
        $iv  = random_bytes(12);
        $tag = '';
        $cipher = openssl_encrypt($value, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
        if (!is_string($cipher)) {
            throw new RuntimeException('No fue posible cifrar el dato MFA.');
        }
        return base64_encode($iv . $tag . $cipher);
    }

    private function decryptWithKey(string $payload, string $keyRaw): string
    {
        $key     = substr(hash('sha256', $keyRaw, true), 0, 32);
        $decoded = base64_decode($payload, true);
        if (!is_string($decoded) || strlen($decoded) < 29) {
            throw new RuntimeException('El dato MFA cifrado no es valido.');
        }
        $plain = openssl_decrypt(
            substr($decoded, 28),
            'aes-256-gcm',
            $key,
            OPENSSL_RAW_DATA,
            substr($decoded, 0, 12),
            substr($decoded, 12, 16)
        );
        if (!is_string($plain)) {
            throw new RuntimeException('No fue posible descifrar el dato MFA.');
        }
        return $plain;
    }

    // ------------------------------------------------------------------
    // DB helpers
    // ------------------------------------------------------------------

    /** @return array<string,mixed>|null */
    private function findMfa(int $firmaId, int $usuarioId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM user_mfa WHERE usuario_id=:uid AND firma_id=:firma_id LIMIT 1'
        );
        $stmt->execute(['uid' => $usuarioId, 'firma_id' => $firmaId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    /** @return array<string,mixed> */
    private function findMfaOrFail(int $firmaId, int $usuarioId): array
    {
        $mfa = $this->findMfa($firmaId, $usuarioId);
        if ($mfa === null) {
            throw new HttpException(404, 'No se encontro configuracion MFA para este usuario.');
        }
        return $mfa;
    }
}
