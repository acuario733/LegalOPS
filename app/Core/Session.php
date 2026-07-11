<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

final class Session
{
    private bool $started = false;

    /** @param array<string, mixed> $config */
    public function __construct(private readonly array $config)
    {
    }

    public function start(): void
    {
        if ($this->started || session_status() === PHP_SESSION_ACTIVE) {
            $this->started = true;
            return;
        }

        if (headers_sent($file, $line)) {
            throw new RuntimeException(sprintf('No se pudo iniciar la sesión: headers enviados en %s:%d.', $file, $line));
        }

        $lifetime = max(1, (int) ($this->config['lifetime_minutes'] ?? 120)) * 60;
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.cookie_httponly', '1');
        ini_set('session.gc_maxlifetime', (string) $lifetime);

        session_name((string) ($this->config['name'] ?? 'legalops_session'));
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'secure' => (bool) ($this->config['secure'] ?? true),
            'httponly' => (bool) ($this->config['http_only'] ?? true),
            'samesite' => $this->validSameSite((string) ($this->config['same_site'] ?? 'Lax')),
        ]);

        if (!session_start()) {
            throw new RuntimeException('No fue posible iniciar una sesión segura.');
        }

        $this->started = true;
        $this->ageFlashData();
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $this->ensureStarted();

        return $_SESSION[$key] ?? $default;
    }

    public function put(string $key, mixed $value): void
    {
        $this->ensureStarted();
        $_SESSION[$key] = $value;
    }

    public function has(string $key): bool
    {
        $this->ensureStarted();

        return array_key_exists($key, $_SESSION);
    }

    public function remove(string $key): void
    {
        $this->ensureStarted();
        unset($_SESSION[$key]);
    }

    public function pull(string $key, mixed $default = null): mixed
    {
        $this->ensureStarted();
        $value = $_SESSION[$key] ?? $default;
        unset($_SESSION[$key]);

        return $value;
    }

    public function flash(string $key, mixed $value): void
    {
        $this->ensureStarted();
        $_SESSION['_flash']['new'][$key] = $value;
    }

    public function getFlash(string $key, mixed $default = null): mixed
    {
        $this->ensureStarted();

        return $_SESSION['_flash']['old'][$key] ?? $default;
    }

    public function regenerate(bool $deleteOldSession = true): void
    {
        $this->ensureStarted();
        if (!session_regenerate_id($deleteOldSession)) {
            throw new RuntimeException('No fue posible rotar el identificador de sesión.');
        }
    }

    public function id(): string
    {
        $this->ensureStarted();

        return session_id();
    }

    public function destroy(): void
    {
        $this->ensureStarted();
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires' => time() - 42000,
                'path' => $params['path'],
                'domain' => $params['domain'],
                'secure' => $params['secure'],
                'httponly' => $params['httponly'],
                'samesite' => $params['samesite'],
            ]);
        }

        session_destroy();
        $this->started = false;
    }

    private function ensureStarted(): void
    {
        if (!$this->started && session_status() !== PHP_SESSION_ACTIVE) {
            $this->start();
        }
    }

    private function ageFlashData(): void
    {
        $new = $_SESSION['_flash']['new'] ?? [];
        $_SESSION['_flash'] = [
            'old' => is_array($new) ? $new : [],
            'new' => [],
        ];
    }

    private function validSameSite(string $value): string
    {
        $normalized = ucfirst(strtolower($value));

        return in_array($normalized, ['Lax', 'Strict', 'None'], true) ? $normalized : 'Lax';
    }
}
