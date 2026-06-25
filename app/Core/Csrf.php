<?php

declare(strict_types=1);

namespace App\Core;

final class Csrf
{
    public function __construct(
        private readonly Session $session,
        private readonly string $sessionKey = '_csrf_token',
        private readonly string $headerName = 'X-CSRF-TOKEN',
        private readonly string $inputName = '_token'
    ) {
    }

    public function token(): string
    {
        $token = $this->session->get($this->sessionKey);
        if (!is_string($token) || strlen($token) < 64) {
            $token = bin2hex(random_bytes(32));
            $this->session->put($this->sessionKey, $token);
        }

        return $token;
    }

    public function regenerate(): string
    {
        $token = bin2hex(random_bytes(32));
        $this->session->put($this->sessionKey, $token);

        return $token;
    }

    public function validate(?string $candidate): bool
    {
        $stored = $this->session->get($this->sessionKey);

        return is_string($stored)
            && is_string($candidate)
            && $candidate !== ''
            && hash_equals($stored, $candidate);
    }

    public function tokenFromRequest(Request $request): ?string
    {
        $header = $request->header($this->headerName);
        if (is_string($header) && $header !== '') {
            return $header;
        }

        $input = $request->input($this->inputName);

        return is_string($input) ? $input : null;
    }

    public function field(): string
    {
        return sprintf(
            '<input type="hidden" name="%s" value="%s">',
            e($this->inputName),
            e($this->token())
        );
    }
}

