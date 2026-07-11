<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use PHPMailer\PHPMailer\Exception as MailException;
use PHPMailer\PHPMailer\PHPMailer;
use RuntimeException;

final class LocalMailService
{
    public function sendPasswordReset(string $email, string $token): void
    {
        $url = rtrim((string) Config::get('app.url', ''), '/') . '/reset-password/' . rawurlencode($token);
        $this->send(
            $email,
            'Recuperación de contraseña LegalOPS Cloud',
            "Use el siguiente enlace antes de su expiración:\n" . $url
        );
    }

    /** @param list<array{name: string, mime: string, content: string}> $attachments */
    public function send(string $email, string $subject, string $body, array $attachments = []): void
    {
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new RuntimeException('La dirección de correo no es válida.');
        }
        $transport = (string) Config::get('mail.transport', 'local');
        if ($transport === 'smtp') {
            $this->sendSmtp($email, $subject, $body, $attachments);
            return;
        }
        if ($transport !== 'local') {
            throw new RuntimeException('El transporte de correo configurado no está disponible.');
        }

        $basePath = dirname(__DIR__, 2);
        $configuredPath = trim((string) Config::get('mail.spool_path', 'storage/temp/mail'));
        $absolute = str_starts_with($configuredPath, '/')
            || preg_match('/^[A-Za-z]:[\\\\\/]/', $configuredPath) === 1;
        $directory = $absolute
            ? $configuredPath
            : $basePath . '/' . ltrim(str_replace('\\', '/', $configuredPath), '/');
        $normalizedDirectory = str_replace('\\', '/', $directory);
        $publicPath = str_replace('\\', '/', $basePath . '/public/');

        if (str_starts_with(rtrim($normalizedDirectory, '/') . '/', $publicPath)) {
            throw new RuntimeException('El transporte local de correo debe permanecer fuera de public/.');
        }
        if (!is_dir($directory) && !mkdir($directory, 0770, true) && !is_dir($directory)) {
            throw new RuntimeException('No fue posible preparar el transporte local de correo.');
        }

        $message = [
            'to' => strtolower(trim($email)),
            'subject' => mb_substr(trim($subject), 0, 200),
            'body' => $body,
            'created_at' => gmdate('c'),
            'attachments' => array_map(static fn (array $attachment): array => [
                'name' => basename($attachment['name']),
                'mime' => $attachment['mime'],
                'content_base64' => base64_encode($attachment['content']),
            ], $attachments),
        ];
        $path = $directory . '/' . bin2hex(random_bytes(16)) . '.json';
        $encoded = json_encode(
            $message,
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );
        if (file_put_contents($path, $encoded, LOCK_EX) === false) {
            throw new RuntimeException('No fue posible encolar el correo.');
        }
    }

    /** @param list<array{name: string, mime: string, content: string}> $attachments */
    private function sendSmtp(string $email, string $subject, string $body, array $attachments): void
    {
        $mailer = new PHPMailer(true);
        try {
            $mailer->isSMTP();
            $mailer->Host = (string) Config::get('mail.host', '127.0.0.1');
            $mailer->Port = (int) Config::get('mail.port', 1025);
            $username = (string) Config::get('mail.username', '');
            $mailer->SMTPAuth = $username !== '';
            $mailer->Username = $username;
            $mailer->Password = (string) Config::get('mail.password', '');
            $mailer->CharSet = 'UTF-8';
            $mailer->setFrom(
                (string) Config::get('mail.from_address', 'no-reply@legalops.local'),
                (string) Config::get('mail.from_name', 'LegalOPS Cloud')
            );
            $mailer->addAddress($email);
            $mailer->Subject = mb_substr(trim($subject), 0, 200);
            $mailer->isHTML(true);
            $mailer->Body = $body;
            $mailer->AltBody = trim(strip_tags($body));
            foreach ($attachments as $attachment) {
                $mailer->addStringAttachment(
                    $attachment['content'],
                    basename($attachment['name']),
                    PHPMailer::ENCODING_BASE64,
                    $attachment['mime']
                );
            }
            $mailer->send();
        } catch (MailException $exception) {
            throw new RuntimeException('No fue posible enviar el correo por SMTP: ' . $exception->getMessage(), 0, $exception);
        }
    }
}
