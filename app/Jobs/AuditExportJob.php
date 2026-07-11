<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Repositories\NotificacionRepository;
use App\Services\StorageService;
use PDO;

final class AuditExportJob extends Job
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly StorageService $storage,
        private readonly NotificacionRepository $notifications
    ) {
    }

    public function handle(array $payload): void
    {
        $firmaId = (int) ($payload['firma_id'] ?? 0);
        $userId = (int) ($payload['usuario_id'] ?? 0);
        $filters = is_array($payload['filtros'] ?? null) ? $payload['filtros'] : [];
        $where = ['a.firma_id=:firma_id'];
        $params = ['firma_id' => $firmaId];
        foreach (['modulo', 'accion'] as $field) {
            $value = trim((string) ($filters[$field] ?? ''));
            if ($value !== '') {
                $where[] = 'a.' . $field . '=:' . $field;
                $params[$field] = mb_substr($value, 0, 100);
            }
        }
        foreach (['desde' => '>=', 'hasta' => '<='] as $field => $operator) {
            $value = (string) ($filters[$field] ?? '');
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1) {
                $where[] = 'a.created_at' . $operator . ':' . $field;
                $params[$field] = $value . ($field === 'desde' ? ' 00:00:00' : ' 23:59:59');
            }
        }
        $statement = $this->pdo->prepare(
            'SELECT a.created_at,u.nombre AS usuario,a.accion,a.modulo,
                    CONCAT(COALESCE(a.entidad_tipo,\'\'),\'#\',COALESCE(a.entidad_id,\'\')) AS entidad,a.ip_address
             FROM auditoria a LEFT JOIN usuarios u ON u.id=a.usuario_id
             WHERE ' . implode(' AND ', $where) . ' ORDER BY a.created_at,a.id'
        );
        $statement->execute($params);
        $rows = $statement->fetchAll();
        $stream = fopen('php://temp', 'w+b');
        if ($stream === false) {
            throw new \RuntimeException('No fue posible crear la exportacion.');
        }
        fwrite($stream, "\xEF\xBB\xBF");
        fputcsv($stream, ['fecha', 'usuario', 'accion', 'modulo', 'entidad', 'ip_address']);
        foreach ($rows as $row) {
            fputcsv($stream, array_map($this->safeCell(...), array_values($row)));
        }
        rewind($stream);
        $csv = stream_get_contents($stream);
        fclose($stream);
        if (!is_string($csv)) {
            throw new \RuntimeException('No fue posible finalizar la exportacion.');
        }
        $key = $this->storage->upload(
            $firmaId,
            'exports/auditoria/auditoria-' . gmdate('Ymd-His') . '-' . bin2hex(random_bytes(4)) . '.csv',
            $csv,
            'text/csv'
        );
        $insert = $this->pdo->prepare(
            'INSERT INTO exportaciones
             (firma_id,usuario_id,tipo,filtros_json,estado,archivo_path,s3_key,filas,expires_at,created_at)
             VALUES (:firma_id,:usuario_id,\'auditoria\',:filtros,\'generada\',NULL,:s3_key,:filas,:expires,CURRENT_TIMESTAMP)'
        );
        $insert->execute([
            'firma_id' => $firmaId,
            'usuario_id' => $userId,
            'filtros' => json_encode($filters, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            's3_key' => $key,
            'filas' => count($rows),
            'expires' => date('Y-m-d H:i:s', time() + 86400),
        ]);
        $exportId = (int) $this->pdo->lastInsertId();
        $this->notifications->createIfMissing([
            'firma_id' => $firmaId,
            'usuario_id' => $userId,
            'titulo' => 'Exportacion de auditoria lista',
            'mensaje' => 'El archivo CSV estara disponible durante 24 horas.',
            'severidad' => 'info',
            'origen_tipo' => 'exportacion',
            'origen_id' => $exportId,
            'origen_url' => $this->storage->presignedUrl($key, 86400),
            'dedupe_key' => hash('sha256', 'audit_export|' . $exportId . '|' . $userId),
        ]);
    }

    private function safeCell(mixed $value): string
    {
        $cell = (string) ($value ?? '');

        return preg_match('/^[=+\-@]/', $cell) === 1 ? "'" . $cell : $cell;
    }
}
