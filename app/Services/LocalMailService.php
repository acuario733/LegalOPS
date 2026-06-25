<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use RuntimeException;

final class LocalMailService
{
    public function sendPasswordReset(string $email, string $token): void
    {
        $basePath = dirname(__DIR__, 2);
        if (Config::get('mail.transport', 'local') !== 'local') {
            throw new RuntimeException('El transporte de correo configurado no está disponible.');
        }
        $configuredPath = trim((string) Config::get('mail.spool_path', 'storage/temp/mail'));
        $absolute = str_starts_with($configuredPath, '/') || preg_match('/^[A-Za-z]:[\\\\\/]/', $configuredPath) === 1;
        $directory = $absolute ? $configuredPath : $basePath . '/' . ltrim(str_replace('\\', '/', $configuredPath), '/');
        $normalizedDirectory = str_replace('\\', '/', $directory);
        $publicPath = str_replace('\\', '/', $basePath . '/public/');
        if (str_starts_with(rtrim($normalizedDirectory, '/') . '/', $publicPath)) {
            throw new RuntimeException('El transporte local de correo debe permanecer fuera de public/.');
        }
        if (!is_dir($directory) && !mkdir($directory, 0770, true) && !is_dir($directory)) {
            throw new RuntimeException('No fue posible preparar el transporte local de correo.');
        }
        $url = rtrim((string) Config::get('app.url', ''), '/') . '/reset-password/' . rawurlencode($token);
        $message = [
            'to' => $email,
            'subject' => 'Recuperación de contraseña LegalOPS Cloud',
            'body' => "Use el siguiente enlace antes de su expiración:\n" . $url,
            'created_at' => gmdate('c'),
        ];
        $path = $directory . '/' . bin2hex(random_bytes(16)) . '.json';
        $encoded = json_encode($message, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (file_put_contents($path, $encoded, LOCK_EX) === false) {
            throw new RuntimeException('No fue posible encolar el correo de recuperación.');
        }
    }
}
