<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Request;
use App\Repositories\UsuarioCambiosSensiblesRepository;

/**
 * Punto único para registrar y leer el historial de cambios sensibles/administrativos
 * de un usuario (tabla usuario_cambios_sensibles). Antes de la Sesión 8 del módulo
 * "Mi perfil", cada servicio (PerfilService, UsuarioService) escribía su propia
 * versión de esta lógica de forma duplicada; este servicio la centraliza.
 *
 * Campos considerados sensibles (se enmascaran y se guarda su hash SHA-256, según
 * exige la restricción chk_usuario_cambios_dato_sensible de la migración 0416):
 * - numero_documento
 * - numero_tarjeta_profesional
 *
 * Cualquier otro campo (cargo, email, tipo, estado, roles, etc.) se guarda en texto
 * plano truncado a 255 caracteres, sin hash, porque la restricción de base de datos
 * no lo exige y no son datos de identificación sensibles en el mismo sentido.
 */
final class UsuarioCambiosSensiblesService
{
    /** @var list<string> */
    private const CAMPOS_SENSIBLES = ['numero_documento', 'numero_tarjeta_profesional'];

    public function __construct(
        private readonly UsuarioCambiosSensiblesRepository $repository,
        private readonly SensitiveDataService $sensitive
    ) {
    }

    /**
     * Registra un cambio de campo si el valor efectivamente cambió. No hace nada si
     * la firma es null (usuario sin firma activa, p.ej. superadmin) o si antes y
     * después son iguales tras normalizar espacios.
     */
    public function registrar(
        ?int $firmaId,
        int $usuarioAfectadoId,
        ?int $usuarioActorId,
        string $campo,
        ?string $valorAnterior,
        ?string $valorNuevo,
        string $origen,
        Request $request
    ): void {
        if ($firmaId === null) {
            return;
        }

        $anterior = trim((string) $valorAnterior);
        $nuevo = trim((string) $valorNuevo);
        if ($anterior === $nuevo) {
            return;
        }

        [$anteriorGuardado, $nuevoGuardado, $hashAnterior, $hashNuevo] = $this->prepararValores($campo, $anterior, $nuevo);

        $this->repository->insert([
            'firma_id' => $firmaId,
            'usuario_afectado_id' => $usuarioAfectadoId,
            'usuario_actor_id' => $usuarioActorId,
            'campo' => $campo,
            'valor_anterior_enmascarado' => $anteriorGuardado,
            'valor_nuevo_enmascarado' => $nuevoGuardado,
            'valor_anterior_hash' => $hashAnterior,
            'valor_nuevo_hash' => $hashNuevo,
            'origen' => $origen,
            'ip_address' => $request->ip(),
            'user_agent' => mb_substr($request->userAgent(), 0, 255),
        ]);
    }

    /** @return array{items: list<array<string, mixed>>, total: int} */
    public function historial(int $firmaId, int $usuarioId, int $limit = 50, int $offset = 0): array
    {
        return [
            'items' => $this->repository->historyForUser($firmaId, $usuarioId, $limit, $offset),
            'total' => $this->repository->countForUser($firmaId, $usuarioId),
        ];
    }

    /** @return array{0: ?string, 1: ?string, 2: ?string, 3: ?string} */
    private function prepararValores(string $campo, string $anterior, string $nuevo): array
    {
        if (in_array($campo, self::CAMPOS_SENSIBLES, true)) {
            $masker = $campo === 'numero_tarjeta_profesional'
                ? $this->sensitive->maskProfessionalCard(...)
                : $this->sensitive->maskDocument(...);

            return [
                $masker($anterior),
                $masker($nuevo),
                $this->sensitive->hash($anterior),
                $this->sensitive->hash($nuevo),
            ];
        }

        return [
            $anterior === '' ? null : mb_substr($anterior, 0, 255),
            $nuevo === '' ? null : mb_substr($nuevo, 0, 255),
            null,
            null,
        ];
    }
}
