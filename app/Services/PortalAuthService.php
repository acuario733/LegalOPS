<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\Config;
use App\Core\HttpException;
use App\Jobs\SendEmailJob;
use PDO;

final class PortalAuthService
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly QueueService $queue,
        private readonly Auth $auth
    ) {
    }

    public function invite(int $firmaId, int $clientId): void
    {
        $statement = $this->pdo->prepare(
            'SELECT id,email,nombre_razon_social FROM clientes
             WHERE id=:id AND firma_id=:firma_id AND deleted_at IS NULL'
        );
        $statement->execute(['id' => $clientId, 'firma_id' => $firmaId]);
        $client = $statement->fetch();
        if (!is_array($client) || filter_var($client['email'], FILTER_VALIDATE_EMAIL) === false) {
            throw new HttpException(422, 'El cliente requiere un email valido para acceder al portal.');
        }
        $token = bin2hex(random_bytes(32));
        $upsert = $this->pdo->prepare(
            'INSERT INTO portal_credenciales
             (firma_id,cliente_id,email,portal_activacion_token,portal_activacion_expires_at,created_at,updated_at)
             VALUES (:firma_id,:cliente_id,:email,:token,:expires,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP)
             ON DUPLICATE KEY UPDATE email=VALUES(email),portal_activacion_token=VALUES(portal_activacion_token),
                 portal_activacion_expires_at=VALUES(portal_activacion_expires_at),updated_at=CURRENT_TIMESTAMP'
        );
        $upsert->execute([
            'firma_id' => $firmaId,
            'cliente_id' => $clientId,
            'email' => strtolower((string) $client['email']),
            'token' => $token,
            'expires' => date('Y-m-d H:i:s', time() + 172800),
        ]);
        $url = rtrim((string) Config::get('app.url', ''), '/') . '/portal/activar/' . $token;
        $this->queue->dispatch(SendEmailJob::class, [
            'to' => (string) $client['email'],
            'subject' => 'Active su acceso al portal LegalOPS',
            'body' => '<p>Hola ' . htmlspecialchars((string) $client['nombre_razon_social']) . '.</p>'
                . '<p><a href="' . htmlspecialchars($url) . '">Activar portal</a>. El enlace vence en 48 horas.</p>',
        ], 'mail');
    }

    /** @return array<string, mixed> */
    public function activation(string $token): array
    {
        $statement = $this->pdo->prepare(
            'SELECT pc.*,f.nombre AS firma_nombre,cl.nombre_razon_social AS cliente_nombre
             FROM portal_credenciales pc
             INNER JOIN firmas f ON f.id=pc.firma_id
             INNER JOIN clientes cl ON cl.id=pc.cliente_id AND cl.firma_id=pc.firma_id
             WHERE pc.portal_activacion_token=:token'
        );
        $statement->execute(['token' => $token]);
        $credential = $statement->fetch();
        if (!is_array($credential)) {
            throw new HttpException(404, 'El enlace de activacion no existe.');
        }
        if ((string) $credential['portal_activacion_expires_at'] < date('Y-m-d H:i:s')) {
            throw new HttpException(410, 'El enlace de activacion expiro. Solicite una nueva invitacion.');
        }

        return $credential;
    }

    public function activate(string $token, string $password): void
    {
        $credential = $this->activation($token);
        if (strlen($password) < 12) {
            throw new HttpException(422, 'La contrasena debe tener al menos 12 caracteres.', ['password' => 'Longitud minima: 12.']);
        }
        $statement = $this->pdo->prepare(
            'UPDATE portal_credenciales SET portal_password_hash=:password_hash,portal_activado_at=CURRENT_TIMESTAMP,
             portal_activacion_token=NULL,portal_activacion_expires_at=NULL,updated_at=CURRENT_TIMESTAMP
             WHERE id=:id AND firma_id=:firma_id'
        );
        $statement->execute([
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'id' => (int) $credential['id'],
            'firma_id' => (int) $credential['firma_id'],
        ]);
    }

    public function login(string $email, string $password): void
    {
        $statement = $this->pdo->prepare(
            'SELECT pc.*,u.id AS usuario_id,u.nombre,u.tipo,u.estado
             FROM portal_credenciales pc
             INNER JOIN portal_usuario_clientes puc
               ON puc.firma_id=pc.firma_id AND puc.cliente_id=pc.cliente_id AND puc.estado=\'activo\'
             INNER JOIN usuarios u
               ON u.id=puc.usuario_id AND u.firma_id=puc.firma_id AND u.tipo=\'cliente_externo\'
             WHERE pc.email=:email AND pc.portal_activado_at IS NOT NULL
               AND u.estado=\'activo\' AND u.deleted_at IS NULL
             ORDER BY pc.id LIMIT 1'
        );
        $statement->execute(['email' => strtolower(trim($email))]);
        $credential = $statement->fetch();
        if (!is_array($credential) || !password_verify($password, (string) $credential['portal_password_hash'])) {
            throw new HttpException(401, 'Credenciales del portal invalidas.');
        }
        $this->auth->login([
            'id' => (int) $credential['usuario_id'],
            'firma_id' => (int) $credential['firma_id'],
            'nombre' => (string) $credential['nombre'],
            'email' => (string) $credential['email'],
            'tipo' => 'cliente_externo',
            'estado' => 'activo',
            'permissions' => [],
            'portal_auth' => true,
        ]);
    }
}
