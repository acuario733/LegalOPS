<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Repositories\CasoRepository;
use App\Repositories\ClienteRepository;
use App\Repositories\DocumentoRepository;
use App\Repositories\GastoRepository;
use App\Repositories\HonorarioRepository;
use App\Repositories\PagoRepository;
use App\Repositories\TareaRepository;
use App\Repositories\TerminoRepository;
use App\Repositories\UsuarioRepository;

final class SearchController extends Controller
{
    public function clientes(Request $request): Response
    {
        $items = $this->container->get(ClienteRepository::class)->searchForSelect($this->firmaId(), $this->q($request), $this->limit($request));

        return $this->json(array_map(static fn (array $row): array => [
            'value' => (int) $row['id'],
            'label' => self::clientLabel($row),
        ], $items));
    }

    public function casos(Request $request): Response
    {
        $items = $this->container->get(CasoRepository::class)->searchForSelect($this->firmaId(), $this->q($request), $this->nullableInt($request->query('cliente_id')), $this->limit($request));

        return $this->json(array_map(static fn (array $row): array => [
            'value' => (int) $row['id'],
            'label' => trim((string) $row['titulo'] . (($row['radicado'] ?? '') ? ' - ' . $row['radicado'] : '')),
            'cliente_id' => (int) $row['cliente_id'],
            'cliente' => (string) $row['cliente_nombre'],
        ], $items));
    }

    public function usuarios(Request $request): Response
    {
        $tipo = (string) $request->query('tipo', 'interno');
        $tipo = $tipo === 'cliente_externo' ? 'cliente_externo' : 'interno';
        $items = $this->container->get(UsuarioRepository::class)->searchForSelect($this->firmaId(), $this->q($request), $this->limit($request), $tipo);

        return $this->json(array_map(static fn (array $row): array => [
            'value' => (int) $row['id'],
            'label' => (string) $row['nombre'] . ' - ' . (string) $row['email'],
        ], $items));
    }

    public function usuariosExternos(Request $request): Response
    {
        $items = $this->container->get(UsuarioRepository::class)->searchForSelect($this->firmaId(), $this->q($request), $this->limit($request), 'cliente_externo');

        return $this->json(array_map(static fn (array $row): array => [
            'value' => (int) $row['id'],
            'label' => (string) $row['nombre'] . ' - ' . (string) $row['email'],
        ], $items));
    }

    public function tareas(Request $request): Response
    {
        $items = $this->container->get(TareaRepository::class)->searchForSelect($this->firmaId(), $this->q($request), $this->nullableInt($request->query('caso_id')), $this->limit($request));

        return $this->json(array_map(static fn (array $row): array => [
            'value' => (int) $row['id'],
            'label' => trim((string) $row['titulo'] . (($row['fecha_vencimiento'] ?? '') ? ' (' . (string) $row['fecha_vencimiento'] . ')' : '')),
            'caso_id' => $row['caso_id'] === null ? null : (int) $row['caso_id'],
            'termino_id' => $row['termino_id'] === null ? null : (int) $row['termino_id'],
        ], $items));
    }

    public function terminos(Request $request): Response
    {
        $items = $this->container->get(TerminoRepository::class)->searchForSelect($this->firmaId(), $this->q($request), $this->nullableInt($request->query('caso_id')), $this->limit($request));

        return $this->json(array_map(static fn (array $row): array => [
            'value' => (int) $row['id'],
            'label' => trim((string) $row['titulo'] . ' (' . (string) $row['fecha_vencimiento'] . ')'),
            'caso_id' => $row['caso_id'] === null ? null : (int) $row['caso_id'],
        ], $items));
    }

    public function honorarios(Request $request): Response
    {
        $items = $this->container->get(HonorarioRepository::class)->searchForSelect($this->firmaId(), $this->q($request), $this->nullableInt($request->query('cliente_id')), $request->query('saldo') === '1', $this->limit($request));

        return $this->json(array_map(static fn (array $row): array => [
            'value' => (int) $row['id'],
            'label' => trim((string) $row['concepto'] . ' - ' . (string) $row['cliente_nombre']),
            'cliente_id' => (int) $row['cliente_id'],
            'caso_id' => $row['caso_id'] === null ? null : (int) $row['caso_id'],
            'cliente' => (string) $row['cliente_nombre'],
            'caso' => (string) ($row['caso_titulo'] ?? ''),
            'moneda' => (string) $row['moneda'],
            'saldo' => (float) $row['saldo_pendiente'],
        ], $items));
    }

    public function documentos(Request $request): Response
    {
        $items = $this->container->get(DocumentoRepository::class)->searchForSelect($this->firmaId(), $this->q($request), $this->nullableInt($request->query('cliente_id')), $this->limit($request));

        return $this->json(array_map(static fn (array $row): array => [
            'value' => (int) $row['id'],
            'label' => (string) $row['titulo'],
            'cliente_id' => $row['cliente_id'] === null ? null : (int) $row['cliente_id'],
        ], $items));
    }

    public function pagos(Request $request): Response
    {
        $items = $this->container->get(PagoRepository::class)->searchForSelect($this->firmaId(), $this->q($request), $this->nullableInt($request->query('cliente_id')), $this->limit($request));

        return $this->json(array_map(static fn (array $row): array => [
            'value' => (int) $row['id'],
            'label' => trim('Pago ' . (string) $row['fecha_pago'] . ' - ' . (string) $row['moneda'] . ' ' . (string) $row['monto']),
            'cliente_id' => (int) $row['cliente_id'],
        ], $items));
    }

    public function gastos(Request $request): Response
    {
        $items = $this->container->get(GastoRepository::class)->searchForSelect($this->firmaId(), $this->q($request), $this->nullableInt($request->query('cliente_id')), $this->limit($request));

        return $this->json(array_map(static fn (array $row): array => [
            'value' => (int) $row['id'],
            'label' => trim((string) $row['concepto'] . ' - ' . (string) $row['moneda'] . ' ' . (string) $row['monto']),
            'cliente_id' => (int) $row['cliente_id'],
        ], $items));
    }

    private function q(Request $request): string
    {
        return mb_substr(trim((string) $request->query('q', '')), 0, 180);
    }

    private function limit(Request $request): int
    {
        return max(1, min(50, (int) $request->query('limit', 20)));
    }

    private function nullableInt(mixed $value): ?int
    {
        return filter_var($value, FILTER_VALIDATE_INT) === false ? null : (int) $value;
    }

    private static function maskDocument(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }
        $length = mb_strlen($value);
        if ($length <= 6) {
            return str_repeat('*', $length);
        }

        return mb_substr($value, 0, 2) . str_repeat('*', max(4, $length - 4)) . mb_substr($value, -2);
    }

    /** @param array<string, mixed> $row */
    private static function clientLabel(array $row): string
    {
        $documentType = (string) ($row['tipo_documento'] ?? '');
        if ($documentType === '') {
            return trim((string) $row['nombre_razon_social']);
        }

        return trim((string) $row['nombre_razon_social'] . ' (' . $documentType . ' ' . self::maskDocument((string) ($row['numero_documento'] ?? '')) . ')');
    }

    private function firmaId(): int
    {
        $firmaId = $this->currentFirma();
        if ($firmaId === null) {
            throw new HttpException(403, 'La operacion requiere una firma activa.');
        }

        return (int) $firmaId;
    }
}
