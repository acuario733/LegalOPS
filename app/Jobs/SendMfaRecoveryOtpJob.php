<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\LocalMailService;

/**
 * Job para enviar el OTP de recuperacion MFA por correo.
 * Se despacha a la cola configurada en MFA_RECOVERY_QUEUE (default: mfa.recovery_otp.send).
 */
final class SendMfaRecoveryOtpJob extends Job
{
    /** @param array<string,mixed> $payload */
    public function handle(array $payload): void
    {
        $email = (string) ($payload['email'] ?? '');
        $otp   = (string) ($payload['otp'] ?? '');

        if ($email === '' || $otp === '') {
            return;
        }

        $mailer = new LocalMailService();
        $mailer->send(
            $email,
            'Código de recuperación MFA — LegalOPS Cloud',
            sprintf(
                "Su código de recuperación MFA es:\n\n  %s\n\nEste código expira en 15 minutos.\n\nSi no solicitó este código, ignore este mensaje.",
                $otp
            )
        );
    }
}
