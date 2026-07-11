<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\HttpException;
use App\Core\Request;
use App\Repositories\CasoRepository;
use App\Repositories\CasoTimelineRepository;
use PDO;

final class CasoEtapaService
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly CasoRepository $casos,
        private readonly CasoTimelineRepository $timeline,
        private readonly AuditoriaService $audit
    ) {
    }

    /** @param list<string> $etapas */
    public function crearEtapas(int $firmaId, int $casoId, array $etapas): void
    {
        $this->ensureCase($firmaId, $casoId);
        $orden = 1;
        foreach ($etapas as $nombre) {
            $nombre = mb_substr(trim($nombre), 0, 100);
            if ($nombre === '') {
                continue;
            }
            // TENANT FILTER: firma_id = ?
            $statement = $this->pdo->prepare(
                'INSERT INTO caso_etapas (firma_id,caso_id,nombre,orden,created_at)
                 VALUES (:firma_id,:caso_id,:nombre,:orden,CURRENT_TIMESTAMP(6))
                 ON DUPLICATE KEY UPDATE nombre=VALUES(nombre)'
            );
            $statement->execute(['firma_id' => $firmaId, 'caso_id' => $casoId, 'nombre' => $nombre, 'orden' => $orden++]);
        }
        $first = $this->firstPending($firmaId, $casoId);
        if ($first !== null) {
            $this->casos->setCurrentStage($firmaId, $casoId, (int) $first['id']);
        }
    }

    public function avanzarEtapa(int $firmaId, int $casoId, int $etapaId, Request $request): void
    {
        $case = $this->ensureCase($firmaId, $casoId);
        $stage = $this->findStage($firmaId, $casoId, $etapaId);
        $currentOrder = $case['etapa_actual_id'] === null ? 0 : $this->stageOrder($firmaId, $casoId, (int) $case['etapa_actual_id']);
        if ((int) $stage['orden'] < $currentOrder) {
            throw new HttpException(403, 'No se puede retroceder de etapa sin permiso de edicion.');
        }

        // TENANT FILTER: firma_id = ?
        $complete = $this->pdo->prepare(
            'UPDATE caso_etapas
             SET completada_at=COALESCE(completada_at,CURRENT_TIMESTAMP(6))
             WHERE firma_id=:firma_id AND caso_id=:caso_id AND orden<=:orden AND deleted_at IS NULL'
        );
        $complete->execute(['firma_id' => $firmaId, 'caso_id' => $casoId, 'orden' => (int) $stage['orden']]);
        $this->casos->setCurrentStage($firmaId, $casoId, $etapaId);
        $this->timeline->create([
            'firma_id' => $firmaId,
            'caso_id' => $casoId,
            'autor_usuario_id' => null,
            'fecha_evento' => date('Y-m-d'),
            'tipo_evento' => 'cambio_etapa',
            'titulo' => 'Cambio de etapa: ' . $stage['nombre'],
            'contenido_publico' => null,
            'contenido_interno' => 'Etapa actualizada a ' . $stage['nombre'],
            'visibilidad' => 'interna',
            'critico' => 0,
            'documento_id' => null,
            'estado' => 'activo',
        ]);
        $this->audit->record('CASO_ETAPA_CAMBIADA', 'casos', 'caso', $casoId, [
            'etapa_id' => $etapaId,
            'etapa_nombre' => $stage['nombre'],
        ], $request, $firmaId);
    }

    /** @return list<array<string, mixed>> */
    public function getEtapas(int $firmaId, int $casoId): array
    {
        $this->ensureCase($firmaId, $casoId);
        // TENANT FILTER: firma_id = ?
        $statement = $this->pdo->prepare(
            'SELECT id,firma_id,caso_id,nombre,orden,completada_at,created_at,
                    IF(completada_at IS NULL, "pendiente", "completada") AS estado
             FROM caso_etapas
             WHERE firma_id=:firma_id AND caso_id=:caso_id AND deleted_at IS NULL
             ORDER BY orden ASC'
        );
        $statement->execute(['firma_id' => $firmaId, 'caso_id' => $casoId]);

        return $statement->fetchAll();
    }

    /** @return list<string> */
    public function defaultStages(?string $tipoProceso): array
    {
        $tipo = mb_strtolower(trim((string) $tipoProceso));
        if ($tipo === 'civil') {
            return ['Radicacion', 'Admision', 'Contestacion', 'Pruebas', 'Alegatos', 'Sentencia'];
        }

        return ['Apertura', 'Analisis', 'Ejecucion', 'Cierre'];
    }

    /** @return array<string, mixed> */
    private function ensureCase(int $firmaId, int $casoId): array
    {
        return $this->casos->findForFirma($firmaId, $casoId) ?? throw new HttpException(404, 'El caso no existe en la firma.');
    }

    /** @return array<string, mixed>|null */
    private function firstPending(int $firmaId, int $casoId): ?array
    {
        // TENANT FILTER: firma_id = ?
        $statement = $this->pdo->prepare(
            'SELECT * FROM caso_etapas
             WHERE firma_id=:firma_id AND caso_id=:caso_id AND deleted_at IS NULL
             ORDER BY orden ASC LIMIT 1'
        );
        $statement->execute(['firma_id' => $firmaId, 'caso_id' => $casoId]);
        $row = $statement->fetch();

        return is_array($row) ? $row : null;
    }

    /** @return array<string, mixed> */
    private function findStage(int $firmaId, int $casoId, int $etapaId): array
    {
        // TENANT FILTER: firma_id = ?
        $statement = $this->pdo->prepare(
            'SELECT * FROM caso_etapas
             WHERE id=:id AND firma_id=:firma_id AND caso_id=:caso_id AND deleted_at IS NULL'
        );
        $statement->execute(['id' => $etapaId, 'firma_id' => $firmaId, 'caso_id' => $casoId]);
        $row = $statement->fetch();

        return is_array($row) ? $row : throw new HttpException(404, 'La etapa no existe en el caso.');
    }

    private function stageOrder(int $firmaId, int $casoId, int $etapaId): int
    {
        return (int) $this->findStage($firmaId, $casoId, $etapaId)['orden'];
    }
}
