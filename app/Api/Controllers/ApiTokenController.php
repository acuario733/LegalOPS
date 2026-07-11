<?php

declare(strict_types=1);

namespace App\Api\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;

/**
 * ApiTokenController — CRUD de tokens de acceso para la API pública.
 *
 * Endpoints (requieren sesión web normal):
 *   GET    /configuracion/api-tokens        → listar tokens de la firma
 *   POST   /configuracion/api-tokens        → crear nuevo token
 *   DELETE /configuracion/api-tokens/{id}   → revocar token
 *
 * El token en texto plano solo se muestra UNA VEZ al crearlo.
 * En BD solo se almacena el SHA-256 (token_hash).
 *
 * TENANT FILTER: firma_id en todos los queries.
 */
final class ApiTokenController extends Controller
{
    // ── Listar tokens ────────────────────────────────────────────────────────

    public function index(Request $request): Response
    {
        $firmaId = (int) $this->currentFirma(); // TENANT FILTER: firma_id

        $stmt = $this->container->get(\PDO::class)->prepare(
            'SELECT id, nombre, scopes, ultimo_uso_at, expira_at, revocado_at, created_at
               FROM api_tokens
              WHERE firma_id = :firma_id
              ORDER BY created_at DESC'
        );
        $stmt->execute(['firma_id' => $firmaId]);

        return $this->json($stmt->fetchAll(\PDO::FETCH_ASSOC), 'OK');
    }

    // ── Crear token ──────────────────────────────────────────────────────────

    public function store(Request $request): Response
    {
        $firmaId = (int) $this->currentFirma(); // TENANT FILTER: firma_id
        $data    = $request->input();

        // Validar nombre obligatorio
        $nombre = trim((string) ($data['nombre'] ?? ''));
        if ($nombre === '') {
            return $this->json(null, 'El nombre del token es obligatorio.', 422, ok: false);
        }
        if (mb_strlen($nombre) > 100) {
            return $this->json(null, 'El nombre no puede exceder 100 caracteres.', 422, ok: false);
        }

        // Validar scopes (array de strings o null)
        $scopes = null;
        if (!empty($data['scopes']) && is_array($data['scopes'])) {
            $allowedScopes = [
                'clientes:read', 'clientes:write',
                'prospectos:read', 'prospectos:write',
                'casos:read', 'casos:write',
                'tareas:read', 'tareas:write',
                'billing:read', 'billing:write',
                'documents:read', 'documents:write',
                'webhooks:read', 'webhooks:write',
            ];
            $invalid = array_diff($data['scopes'], $allowedScopes);
            if (!empty($invalid)) {
                return $this->json(
                    null,
                    'Scopes inválidos: ' . implode(', ', $invalid),
                    422,
                    ok: false
                );
            }
            $scopes = json_encode(array_values($data['scopes']));
        }

        // Validar fecha de expiración
        $expiraAt = null;
        if (!empty($data['expira_at'])) {
            $ts = strtotime((string) $data['expira_at']);
            if ($ts === false || $ts <= time()) {
                return $this->json(null, 'La fecha de expiración debe ser futura.', 422, ok: false);
            }
            $expiraAt = date('Y-m-d H:i:s', $ts);
        }

        // Generar token seguro (32 bytes = 64 chars hex)
        $rawToken  = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $rawToken);

        $usuarioId = $this->currentUser()['id'] ?? null;

        $pdo = $this->container->get(\PDO::class);
        $stmt = $pdo->prepare(
            'INSERT INTO api_tokens (firma_id, usuario_id, nombre, token_hash, scopes, expira_at, created_at, updated_at)
             VALUES (:firma_id, :usuario_id, :nombre, :token_hash, :scopes, :expira_at, NOW(6), NOW(6))'
        );
        $stmt->execute([
            'firma_id'   => $firmaId,
            'usuario_id' => $usuarioId,
            'nombre'     => $nombre,
            'token_hash' => $tokenHash,
            'scopes'     => $scopes,
            'expira_at'  => $expiraAt,
        ]);

        $newId = (int) $pdo->lastInsertId();

        // El token en texto plano se devuelve UNA SOLA VEZ — no se puede recuperar después
        return $this->json([
            'id'        => $newId,
            'nombre'    => $nombre,
            'token'     => $rawToken,  // ← solo visible ahora
            'scopes'    => $scopes !== null ? json_decode($scopes, true) : null,
            'expira_at' => $expiraAt,
            'aviso'     => 'Guarda este token en un lugar seguro. No se mostrará de nuevo.',
        ], 'Token creado exitosamente.', 201);
    }

    // ── Revocar token ────────────────────────────────────────────────────────

    public function revoke(Request $request): Response
    {
        $firmaId = (int) $this->currentFirma(); // TENANT FILTER: firma_id
        $tokenId = (int) $request->route('id');

        $stmt = $this->container->get(\PDO::class)->prepare(
            'UPDATE api_tokens
                SET revocado_at = NOW(6), updated_at = NOW(6)
              WHERE id = :id
                AND firma_id = :firma_id   -- TENANT FILTER: garantiza que no revocan tokens ajenos
                AND revocado_at IS NULL'
        );
        $stmt->execute(['id' => $tokenId, 'firma_id' => $firmaId]);

        if ($stmt->rowCount() === 0) {
            return $this->json(null, 'Token no encontrado o ya estaba revocado.', 404, ok: false);
        }

        return $this->json(null, 'Token revocado exitosamente.');
    }
}
