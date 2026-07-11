<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Services\TrustService;

final class TrustController extends Controller
{
    /**
     * Permisos:
     * - trust.ver: consultar saldos, libro y reportes
     * - trust.depositar: registrar depositos
     * - trust.retirar: registrar retiros, restringido a ADMIN/PARTNER por migracion de permisos
     *
     * Contratos JSON mantienen {ok,message,data,errors}.
     */
    public function index(Request $request): Response
    {
        $clienteId = $this->nullableInt($request->query('cliente_id'));
        if ($clienteId === null) {
            return $this->json(['saldo_total' => '0.00', 'cuentas' => []]);
        }

        return $this->json($this->container->get(TrustService::class)->getSaldo($this->firmaId(), $clienteId));
    }

    public function saldo(Request $request, string $clienteId): Response
    {
        return $this->json($this->container->get(TrustService::class)->getSaldo($this->firmaId(), (int) $clienteId));
    }

    public function libro(Request $request): Response
    {
        return $this->json($this->container->get(TrustService::class)->getLibro(
            $this->firmaId(),
            $this->nullableInt($request->query('cliente_id')),
            $this->nullableString($request->query('fecha_desde')),
            $this->nullableString($request->query('fecha_hasta'))
        ));
    }

    public function depositar(Request $request): Response
    {
        $transaction = $this->container->get(TrustService::class)->depositar(
            $this->firmaId(),
            (int) $request->input('cliente_id', 0),
            $this->nullableInt($request->input('caso_id')),
            $request->input('monto', 0),
            (string) $request->input('descripcion', ''),
            $this->nullableString($request->input('referencia')),
            $request
        );

        return $this->json($transaction, 'Deposito trust registrado correctamente.', 201);
    }

    public function retirar(Request $request): Response
    {
        $transaction = $this->container->get(TrustService::class)->retirar(
            $this->firmaId(),
            (int) $request->input('trust_account_id', 0),
            $request->input('monto', 0),
            (string) $request->input('descripcion', ''),
            $this->nullableString($request->input('referencia')),
            $this->nullableInt($request->input('cliente_id')),
            $request
        );

        return $this->json($transaction, 'Retiro trust registrado correctamente.', 201);
    }

    public function reporteConciliacion(Request $request): Response
    {
        $periodo = (string) $request->query('mes', date('Y-m'));
        $s3Key = $this->container->get(TrustService::class)->generarReporteConciliacion($this->firmaId(), $periodo);

        return $this->json(['s3_key' => $s3Key], 'Reporte de conciliacion generado correctamente.');
    }

    private function firmaId(): int
    {
        $firmaId = $this->currentFirma();
        if ($firmaId === null) {
            throw new HttpException(403, 'La operacion requiere una firma activa.');
        }

        return (int) $firmaId;
    }

    private function nullableInt(mixed $value): ?int
    {
        return filter_var($value, FILTER_VALIDATE_INT) === false ? null : (int) $value;
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));

        return $value === '' ? null : $value;
    }
}
