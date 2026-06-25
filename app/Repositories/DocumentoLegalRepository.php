<?php

declare(strict_types=1);

namespace App\Repositories;

final class DocumentoLegalRepository extends BaseRepository
{
    /** @return list<array<string, mixed>> */
    public function all(): array
    {
        return $this->pdo->query(
            'SELECT id,tipo,version,titulo,checksum,estado,published_at,published_by_usuario_id,created_at
             FROM documentos_legales ORDER BY tipo,created_at DESC'
        )->fetchAll();
    }

    /** @return array<string, mixed>|null */
    public function find(int $id): ?array
    {
        $statement = $this->pdo->prepare('SELECT * FROM documentos_legales WHERE id=:id');
        $statement->execute(['id' => $id]);
        $row = $statement->fetch();

        return is_array($row) ? $row : null;
    }

    public function typeVersionExists(string $type, string $version): bool
    {
        $statement = $this->pdo->prepare('SELECT COUNT(*) FROM documentos_legales WHERE tipo=:tipo AND version=:version');
        $statement->execute(['tipo' => $type, 'version' => $version]);

        return (int) $statement->fetchColumn() > 0;
    }

    /** @param array<string, mixed> $data */
    public function create(array $data): int
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO documentos_legales (tipo,version,titulo,contenido,checksum,estado,created_at,updated_at)
             VALUES (:tipo,:version,:titulo,:contenido,:checksum,\'borrador\',CURRENT_TIMESTAMP(6),CURRENT_TIMESTAMP(6))'
        );
        $statement->execute($data);

        return (int) $this->pdo->lastInsertId();
    }

    public function publish(int $id, string $type, int $publisherId): void
    {
        $archive = $this->pdo->prepare(
            'UPDATE documentos_legales SET estado=\'archivado\',updated_at=CURRENT_TIMESTAMP(6)
             WHERE tipo=:tipo AND estado=\'vigente\' AND id<>:id'
        );
        $archive->execute(['tipo' => $type, 'id' => $id]);
        $publish = $this->pdo->prepare(
            'UPDATE documentos_legales SET estado=\'vigente\',published_at=CURRENT_TIMESTAMP(6),published_by_usuario_id=:usuario_id,updated_at=CURRENT_TIMESTAMP(6)
             WHERE id=:id AND estado=\'borrador\''
        );
        $publish->execute(['usuario_id' => $publisherId, 'id' => $id]);
    }

    /** @return list<array<string, mixed>> */
    public function pendingForUser(int $userId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT d.id,d.tipo,d.version,d.titulo,d.contenido,d.checksum,d.published_at
             FROM documentos_legales d
             LEFT JOIN aceptaciones_legales a ON a.documento_legal_id=d.id AND a.usuario_id=:usuario_id
             WHERE d.estado=\'vigente\' AND a.id IS NULL ORDER BY d.tipo'
        );
        $statement->execute(['usuario_id' => $userId]);

        return $statement->fetchAll();
    }
}
