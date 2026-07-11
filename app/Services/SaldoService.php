<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\HttpException;
use App\Repositories\CasoRepository;
use App\Repositories\ClienteRepository;
use App\Repositories\SaldoRepository;

final class SaldoService
{
    public function __construct(
        private readonly SaldoRepository $repository,
        private readonly ClienteRepository $clientes,
        private readonly CasoRepository $casos
    ) {
    }

    /** @return array{items: list<array<string, mixed>>, total: int, page: int, per_page: int} */
    public function listByCliente(int $firmaId, int $page = 1, int $perPage = 25): array
    {
        $result = $this->repository->paginateClientes($firmaId, max(1, $page), min(100, max(1, $perPage)));
        $result['page'] = max(1, $page);
        $result['per_page'] = min(100, max(1, $perPage));
        $result['formula'] = $this->repository->formula();

        return $result;
    }

    /** @return array<string, mixed> */
    public function resumen(int $firmaId, ?int $clienteId = null, ?int $casoId = null): array
    {
        if ($clienteId !== null && $this->clientes->findForFirma($firmaId, $clienteId) === null) {
            throw new HttpException(422, 'El cliente seleccionado no pertenece a la firma.');
        }
        if ($casoId !== null) {
            $case = $this->casos->findForFirma($firmaId, $casoId) ?? throw new HttpException(422, 'El caso seleccionado no pertenece a la firma.');
            if ($clienteId !== null && (int) $case['cliente_id'] !== $clienteId) {
                throw new HttpException(422, 'El caso no pertenece al cliente seleccionado.');
            }
            $clienteId ??= (int) $case['cliente_id'];
        }

        return $this->repository->resumen($firmaId, $clienteId, $casoId);
    }

    /** @return array<string, mixed> */
    public function resumenCliente(int $firmaId, int $clienteId): array
    {
        if ($this->clientes->findForFirma($firmaId, $clienteId) === null) {
            throw new HttpException(422, 'El cliente seleccionado no pertenece a la firma.');
        }

        return $this->repository->resumenCliente($firmaId, $clienteId);
    }

    /** @return array<string, mixed> */
    public function resumenCaso(int $firmaId, int $casoId): array
    {
        $case = $this->casos->findForFirma($firmaId, $casoId) ?? throw new HttpException(422, 'El caso seleccionado no pertenece a la firma.');
        $result = $this->repository->resumenCaso($firmaId, $casoId);
        $result['cliente_id'] = (int) $case['cliente_id'];

        return $result;
    }

    /** @return array{honorarios: list<string>, pagos: list<string>, gastos: list<string>, expresion: string} */
    public function formula(): array
    {
        return $this->repository->formula();
    }
}
