<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\HttpException;
use PDO;

final class CalendarioEventoService
{
    public function __construct(private readonly PDO $pdo, private readonly Auth $auth)
    {
    }

    /** @return list<array<string, mixed>> */
    public function rango(int $firmaId, string $inicio, string $fin): array
    {
        // TENANT FILTER: firma_id = ?
        $statement = $this->pdo->prepare(
            'SELECT id,titulo,descripcion,inicio_at AS inicio,fin_at AS fin,todo_el_dia,tipo,lugar,asistentes,external_id,color
             FROM calendario_eventos
             WHERE firma_id=:firma_id AND deleted_at IS NULL AND inicio_at<=:fin AND fin_at>=:inicio
             ORDER BY inicio_at ASC'
        );
        $statement->execute(['firma_id' => $firmaId, 'inicio' => $inicio, 'fin' => $fin]);

        return $statement->fetchAll();
    }

    /** @param array<string, mixed> $data */
    public function crear(int $firmaId, array $data): int
    {
        $payload = $this->normalize($data);
        // TENANT FILTER: firma_id = ?
        $statement = $this->pdo->prepare(
            'INSERT INTO calendario_eventos
             (firma_id,caso_id,titulo,descripcion,inicio_at,fin_at,todo_el_dia,tipo,lugar,asistentes,external_id,color,created_by_usuario_id,created_at,updated_at)
             VALUES
             (:firma_id,:caso_id,:titulo,:descripcion,:inicio_at,:fin_at,:todo_el_dia,:tipo,:lugar,:asistentes,NULL,:color,:usuario_id,CURRENT_TIMESTAMP(6),CURRENT_TIMESTAMP(6))'
        );
        $statement->execute($payload + ['firma_id' => $firmaId, 'usuario_id' => $this->auth->id()]);

        return (int) $this->pdo->lastInsertId();
    }

    /** @param array<string, mixed> $data */
    public function actualizar(int $firmaId, int $id, array $data): void
    {
        $payload = $this->normalize($data);
        // TENANT FILTER: firma_id = ?
        $statement = $this->pdo->prepare(
            'UPDATE calendario_eventos
             SET caso_id=:caso_id,titulo=:titulo,descripcion=:descripcion,inicio_at=:inicio_at,fin_at=:fin_at,
                 todo_el_dia=:todo_el_dia,tipo=:tipo,lugar=:lugar,asistentes=:asistentes,color=:color,updated_at=CURRENT_TIMESTAMP(6)
             WHERE id=:id AND firma_id=:firma_id AND deleted_at IS NULL'
        );
        $statement->execute($payload + ['id' => $id, 'firma_id' => $firmaId]);
    }

    public function eliminar(int $firmaId, int $id): void
    {
        // TENANT FILTER: firma_id = ?
        $statement = $this->pdo->prepare('UPDATE calendario_eventos SET deleted_at=CURRENT_TIMESTAMP(6),updated_at=CURRENT_TIMESTAMP(6) WHERE id=:id AND firma_id=:firma_id AND deleted_at IS NULL');
        $statement->execute(['id' => $id, 'firma_id' => $firmaId]);
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    private function normalize(array $data): array
    {
        $titulo = trim((string) ($data['titulo'] ?? ''));
        $inicio = trim((string) ($data['inicio_at'] ?? $data['inicio'] ?? ''));
        $fin = trim((string) ($data['fin_at'] ?? $data['fin'] ?? ''));
        if ($titulo === '' || $inicio === '' || $fin === '') {
            throw new HttpException(422, 'Titulo, inicio y fin son obligatorios.');
        }
        $tipo = (string) ($data['tipo'] ?? 'otro');

        return [
            'caso_id' => filter_var($data['caso_id'] ?? null, FILTER_VALIDATE_INT) ?: null,
            'titulo' => mb_substr($titulo, 0, 255),
            'descripcion' => $this->nullable($data['descripcion'] ?? null, 2000),
            'inicio_at' => mb_substr($inicio, 0, 26),
            'fin_at' => mb_substr($fin, 0, 26),
            'todo_el_dia' => in_array(($data['todo_el_dia'] ?? false), [1, '1', true, 'true', 'on'], true) ? 1 : 0,
            'tipo' => in_array($tipo, ['audiencia', 'termino', 'tarea', 'reunion', 'otro'], true) ? $tipo : 'otro',
            'lugar' => $this->nullable($data['lugar'] ?? null, 255),
            'asistentes' => json_encode(is_array($data['asistentes'] ?? null) ? $data['asistentes'] : [], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            'color' => preg_match('/^#[0-9A-Fa-f]{6}$/', (string) ($data['color'] ?? '')) === 1 ? $data['color'] : null,
        ];
    }

    private function nullable(mixed $value, int $max): ?string
    {
        $value = trim((string) ($value ?? ''));

        return $value === '' ? null : mb_substr($value, 0, $max);
    }
}
