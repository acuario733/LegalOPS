<?php

declare(strict_types=1);

namespace App\Repositories;

final class DocumentoVersionRepository extends BaseRepository
{
    private ?bool $hasS3Columns = null;

    public function nextNumber(int $firmaId, int $documentId): int
    {
        $statement = $this->pdo->prepare('SELECT COALESCE(MAX(version_numero), 0) + 1 FROM documento_versiones WHERE firma_id=:firma_id AND documento_id=:documento_id');
        $statement->execute(['firma_id' => $firmaId, 'documento_id' => $documentId]);

        return (int) $statement->fetchColumn();
    }

    /** @return list<array<string, mixed>> */
    public function allForDocument(int $firmaId, int $documentId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT v.*, u.nombre AS uploaded_by_nombre
             FROM documento_versiones v
             LEFT JOIN usuarios u ON u.id=v.uploaded_by_usuario_id
             WHERE v.firma_id=:firma_id AND v.documento_id=:documento_id
             ORDER BY v.version_numero DESC'
        );
        $statement->execute(['firma_id' => $firmaId, 'documento_id' => $documentId]);

        return $statement->fetchAll();
    }

    /** @return array<string, mixed>|null */
    public function findForDocument(int $firmaId, int $documentId, int $id): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT * FROM documento_versiones
             WHERE id=:id AND firma_id=:firma_id AND documento_id=:documento_id'
        );
        $statement->execute(['id' => $id, 'firma_id' => $firmaId, 'documento_id' => $documentId]);
        $row = $statement->fetch();

        return is_array($row) ? $row : null;
    }

    /** @return array<string, mixed>|null */
    public function findCurrent(int $firmaId, int $documentId): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT v.*
             FROM documentos d
             INNER JOIN documento_versiones v ON v.id=d.current_version_id AND v.documento_id=d.id AND v.firma_id=d.firma_id
             WHERE d.id=:documento_id AND d.firma_id=:firma_id AND d.deleted_at IS NULL'
        );
        $statement->execute(['documento_id' => $documentId, 'firma_id' => $firmaId]);
        $row = $statement->fetch();

        return is_array($row) ? $row : null;
    }

    /** @param array<string, mixed> $data */
    public function create(array $data): int
    {
        $data += ['s3_key' => null, 's3_bucket' => null];
        if (!$this->hasS3Columns()) {
            unset($data['s3_key'], $data['s3_bucket']);
            $statement = $this->pdo->prepare(
                'INSERT INTO documento_versiones
                (firma_id,documento_id,version_numero,nombre_original,nombre_fisico,extension,mime_declarado,mime_detectado,size_bytes,checksum_sha256,storage_path,uploaded_by_usuario_id,created_at)
                 VALUES
                (:firma_id,:documento_id,:version_numero,:nombre_original,:nombre_fisico,:extension,:mime_declarado,:mime_detectado,:size_bytes,:checksum_sha256,:storage_path,:uploaded_by_usuario_id,CURRENT_TIMESTAMP)'
            );
            $statement->execute($data);

            return (int) $this->pdo->lastInsertId();
        }

        $statement = $this->pdo->prepare(
            'INSERT INTO documento_versiones
            (firma_id,documento_id,version_numero,nombre_original,nombre_fisico,extension,mime_declarado,mime_detectado,size_bytes,checksum_sha256,storage_path,s3_key,s3_bucket,uploaded_by_usuario_id,created_at)
             VALUES
            (:firma_id,:documento_id,:version_numero,:nombre_original,:nombre_fisico,:extension,:mime_declarado,:mime_detectado,:size_bytes,:checksum_sha256,:storage_path,:s3_key,:s3_bucket,:uploaded_by_usuario_id,CURRENT_TIMESTAMP)'
        );
        $statement->execute($data);

        return (int) $this->pdo->lastInsertId();
    }

    private function hasS3Columns(): bool
    {
        if ($this->hasS3Columns !== null) {
            return $this->hasS3Columns;
        }

        try {
            $this->pdo->query('SELECT s3_key,s3_bucket FROM documento_versiones WHERE 1=0');
            return $this->hasS3Columns = true;
        } catch (\Throwable) {
            return $this->hasS3Columns = false;
        }
    }
}
