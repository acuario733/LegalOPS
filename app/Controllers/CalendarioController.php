<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Services\CalendarioEventoService;

final class CalendarioController extends Controller
{
    public function index(Request $request): Response
    {
        return $this->view('calendario/index', [
            'title'       => 'Calendario',
            'csrfToken'   => $this->csrf->token(),
            'currentUser' => $this->currentUser(),
        ]);
    }

    /**
     * GET /api/calendario/eventos?mes=YYYY-MM
     * Devuelve eventos de audiencias, términos y booking del mes solicitado.
     * TENANT FILTER: firma_id = firmaId()
     */
    public function eventos(Request $request): Response
    {
        $firmaId = $this->resolvedFirmaId();

        $mes = $request->query('mes', date('Y-m'));
        if (!preg_match('/^\d{4}-\d{2}$/', (string) $mes)) {
            return $this->json(['error' => 'Parámetro mes inválido. Formato esperado: YYYY-MM'], 'Parámetro mes inválido.', 400);
        }

        [$year, $month] = explode('-', (string) $mes);
        $from = "{$year}-{$month}-01";
        $to   = date('Y-m-t', strtotime($from)); // último día del mes

        $pdo    = $this->container->get(\PDO::class);
        $events = [];

        // ── Audiencias ─────────────────────────────────────────────────────
        // TENANT FILTER: firma_id = ?
        $stmt = $pdo->prepare(
            'SELECT id, titulo, fecha_audiencia AS fecha, expediente_id
               FROM audiencias
              WHERE firma_id = ?
                AND fecha_audiencia BETWEEN ? AND ?
              ORDER BY fecha_audiencia'
        );
        $stmt->execute([$firmaId, $from, $to]);
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $events[] = [
                'id'    => 'audiencia-' . $row['id'],
                'title' => $row['titulo'] ?? 'Audiencia',
                'start' => $row['fecha'],
                'color' => '#DC2626',
                'tipo'  => 'audiencia',
                'url'   => $row['expediente_id'] ? '/casos/' . $row['expediente_id'] : null,
            ];
        }

        // ── Términos ───────────────────────────────────────────────────────
        // TENANT FILTER: firma_id = ?
        $stmt = $pdo->prepare(
            'SELECT id, descripcion, fecha_vencimiento AS fecha, caso_id
               FROM terminos
              WHERE firma_id = ?
                AND fecha_vencimiento BETWEEN ? AND ?
              ORDER BY fecha_vencimiento'
        );
        $stmt->execute([$firmaId, $from, $to]);
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $events[] = [
                'id'    => 'termino-' . $row['id'],
                'title' => $row['descripcion'] ?? 'Término',
                'start' => $row['fecha'],
                'color' => '#D97706',
                'tipo'  => 'termino',
                'url'   => $row['caso_id'] ? '/casos/' . $row['caso_id'] : null,
            ];
        }

        // ── Citas / Booking ────────────────────────────────────────────────
        // TENANT FILTER: via booking_configs.firma_id = ?
        $stmt = $pdo->prepare(
            'SELECT a.id, a.nombre_cliente, a.starts_at, a.estado
               FROM booking_appointments a
               JOIN booking_configs c ON c.id = a.booking_config_id
              WHERE c.firma_id = ?
                AND DATE(a.starts_at) BETWEEN ? AND ?
                AND a.estado != "cancelada"
              ORDER BY a.starts_at'
        );
        $stmt->execute([$firmaId, $from, $to]);
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $events[] = [
                'id'    => 'cita-' . $row['id'],
                'title' => 'Cita: ' . ($row['nombre_cliente'] ?? ''),
                'start' => $row['starts_at'],
                'color' => '#1A6B45',
                'tipo'  => 'cita',
                'url'   => null,
            ];
        }

        foreach ($this->container->get(CalendarioEventoService::class)->rango($firmaId, $from . ' 00:00:00', $to . ' 23:59:59') as $row) {
            $events[] = [
                'id' => 'calendario-' . $row['id'],
                'title' => $row['titulo'],
                'start' => $row['inicio'],
                'end' => $row['fin'],
                'color' => $row['color'] ?: '#2563EB',
                'tipo' => $row['tipo'],
                'url' => null,
            ];
        }

        return $this->json(['eventos' => $events]);
    }

    public function store(Request $request): Response
    {
        $id = $this->container->get(CalendarioEventoService::class)->crear($this->resolvedFirmaId(), (array) $request->input());

        return $this->json(['id' => $id], 'Evento creado correctamente.', 201);
    }

    public function update(Request $request, string $id): Response
    {
        $this->container->get(CalendarioEventoService::class)->actualizar($this->resolvedFirmaId(), (int) $id, (array) $request->input());

        return $this->json(null, 'Evento actualizado correctamente.');
    }

    public function destroy(Request $request, string $id): Response
    {
        $this->container->get(CalendarioEventoService::class)->eliminar($this->resolvedFirmaId(), (int) $id);

        return $this->json(null, 'Evento eliminado correctamente.');
    }

    private function resolvedFirmaId(): int
    {
        $id = $this->currentFirma();
        if ($id === null) {
            throw new HttpException(403, 'La operación requiere una firma activa.');
        }
        return (int) $id;
    }
}
