<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\HttpException;
use App\Core\Request;
use App\Repositories\CasoRepository;
use App\Repositories\ClienteRepository;
use App\Repositories\DocumentoRepository;
use App\Repositories\GastoRepository;
use App\Repositories\HonorarioRepository;
use App\Repositories\PagoRepository;
use App\Repositories\PortalAutorizacionRepository;
use App\Repositories\UsuarioRepository;
use App\Validators\PortalAutorizacionValidator;

final class PortalAutorizacionService
{
    public function __construct(
        private readonly PortalAutorizacionRepository $repository,
        private readonly ClienteRepository $clientes,
        private readonly CasoRepository $casos,
        private readonly DocumentoRepository $documentos,
        private readonly HonorarioRepository $honorarios,
        private readonly PagoRepository $pagos,
        private readonly GastoRepository $gastos,
        private readonly UsuarioRepository $usuarios,
        private readonly PortalAutorizacionValidator $validator,
        private readonly AuditoriaService $audit,
        private readonly Auth $auth
    ) {
    }

    /** @return list<array<string, mixed>> */
    public function overview(int $firmaId, int $clienteId): array
    {
        $this->requireClient($firmaId, $clienteId);

        return $this->repository->overview($firmaId, $clienteId);
    }

    /** @param array<string, mixed> $data */
    public function change(int $firmaId, array $data, Request $request): void
    {
        $normalized = $this->normalize($data);
        if (!$this->validator->validateData($normalized)) {
            throw new HttpException(422, 'Revise la autorizacion del portal.', $this->validator->errors());
        }
        $this->validateResource($firmaId, $normalized);

        $payload = [
            'firma_id' => $firmaId,
            'cliente_id' => $normalized['cliente_id'],
            'recurso_id' => $normalized['recurso_id'],
            'estado' => $normalized['estado'],
            'observacion_publica' => $normalized['observacion_publica'],
            'usuario_id' => $this->auth->id(),
            'revocado_at' => $normalized['estado'] === 'revocado' ? date('Y-m-d H:i:s') : null,
        ];

        match ($normalized['recurso_tipo']) {
            'caso' => $this->repository->upsertCase($payload),
            'documento' => $this->repository->upsertDocument($payload),
            'honorario' => $this->repository->upsertFinance($payload + ['tipo_finanza' => 'honorario'], 'honorario_id'),
            'pago' => $this->repository->upsertFinance($payload + ['tipo_finanza' => 'pago'], 'pago_id'),
            'gasto' => $this->repository->upsertFinance($payload + ['tipo_finanza' => 'gasto'], 'gasto_id'),
            'usuario_cliente' => $this->repository->linkUserClient($firmaId, $normalized['recurso_id'], $normalized['cliente_id'], (int) $this->auth->id(), $normalized['estado']),
            default => throw new HttpException(422, 'Tipo de recurso no valido.'),
        };

        if ($normalized['observacion_publica'] !== null || $normalized['observacion_interna'] !== null) {
            $this->repository->insertObservation(
                $firmaId,
                (int) $normalized['cliente_id'],
                (string) $normalized['recurso_tipo'],
                (int) $normalized['recurso_id'],
                $normalized['observacion_publica'],
                $normalized['observacion_interna'],
                (int) $this->auth->id()
            );
        }

        $this->audit->record('PORTAL_AUTORIZACION_CAMBIADA', 'portal', $normalized['recurso_tipo'], $normalized['recurso_id'], [
            'cliente_id' => $normalized['cliente_id'],
            'estado' => $normalized['estado'],
            'observacion_publica' => $normalized['observacion_publica'] === null ? 'no' : 'si',
            'observacion_interna' => $normalized['observacion_interna'] === null ? 'no' : 'si',
        ], $request, $firmaId, 'warning');
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    private function normalize(array $data): array
    {
        return [
            'cliente_id' => $this->requiredInt($data['cliente_id'] ?? null),
            'recurso_tipo' => (string) ($data['recurso_tipo'] ?? ''),
            'recurso_id' => $this->requiredInt($data['recurso_id'] ?? null),
            'estado' => (string) ($data['estado'] ?? 'autorizado'),
            'observacion_publica' => $this->nullableString($data['observacion_publica'] ?? null, 2000),
            'observacion_interna' => $this->nullableString($data['observacion_interna'] ?? null, 2000),
        ];
    }

    /** @param array<string, mixed> $data */
    private function validateResource(int $firmaId, array $data): void
    {
        $clienteId = (int) $data['cliente_id'];
        $this->requireClient($firmaId, $clienteId);
        $resourceId = (int) $data['recurso_id'];

        $resource = match ($data['recurso_tipo']) {
            'caso' => $this->casos->findForFirma($firmaId, $resourceId),
            'documento' => $this->documentos->findForFirma($firmaId, $resourceId),
            'honorario' => $this->honorarios->findForFirma($firmaId, $resourceId),
            'pago' => $this->pagos->findForFirma($firmaId, $resourceId),
            'gasto' => $this->gastos->findForFirma($firmaId, $resourceId),
            'usuario_cliente' => $this->usuarios->findForFirma($firmaId, $resourceId),
            default => null,
        };
        if (!is_array($resource)) {
            throw new HttpException(422, 'El recurso seleccionado no pertenece a la firma.');
        }
        if (($data['recurso_tipo'] === 'usuario_cliente') && ($resource['tipo'] ?? '') !== 'cliente_externo') {
            throw new HttpException(422, 'Solo usuarios externos pueden asociarse al portal.');
        }
        if ($data['recurso_tipo'] !== 'usuario_cliente' && isset($resource['cliente_id']) && (int) $resource['cliente_id'] !== $clienteId) {
            throw new HttpException(422, 'El recurso no pertenece al cliente seleccionado.');
        }
        if ($data['recurso_tipo'] === 'documento' && empty($resource['cliente_id'])) {
            throw new HttpException(422, 'El documento debe estar asociado a un cliente para publicarse en portal.');
        }
    }

    private function requireClient(int $firmaId, int $clienteId): void
    {
        if ($this->clientes->findForFirma($firmaId, $clienteId) === null) {
            throw new HttpException(422, 'El cliente seleccionado no pertenece a la firma.');
        }
    }

    private function requiredInt(mixed $value): int
    {
        return filter_var($value, FILTER_VALIDATE_INT) === false ? 0 : (int) $value;
    }

    private function nullableString(mixed $value, int $max): ?string
    {
        $value = trim((string) ($value ?? ''));

        return $value === '' ? null : mb_substr($value, 0, $max);
    }
}
