<?php

declare(strict_types=1);

namespace App\Repositories;

final class IntakeFormRepository extends BaseRepository
{
    /** @return list<array<string, mixed>> */
    public function findForFirma(int $firmaId): array
    {
        // TENANT FILTER
        $statement = $this->pdo->prepare(
            'SELECT f.*,
                    (SELECT COUNT(*) FROM intake_submissions s
                     WHERE s.intake_form_id = f.id AND s.firma_id = f.firma_id) AS total_envios
             FROM intake_forms f
             WHERE f.firma_id = :firma_id AND f.deleted_at IS NULL
             ORDER BY f.updated_at DESC, f.id DESC'
        );
        $statement->execute(['firma_id' => $firmaId]);

        return $statement->fetchAll();
    }

    /** @return array<string, mixed>|null */
    public function findById(int $id, int $firmaId): ?array
    {
        // TENANT FILTER
        $statement = $this->pdo->prepare(
            'SELECT f.*,
                    (SELECT COUNT(*) FROM intake_submissions s
                     WHERE s.intake_form_id = f.id AND s.firma_id = f.firma_id) AS total_envios
             FROM intake_forms f
             WHERE f.id = :id AND f.firma_id = :firma_id AND f.deleted_at IS NULL'
        );
        $statement->execute(['id' => $id, 'firma_id' => $firmaId]);
        $row = $statement->fetch();

        return is_array($row) ? $row : null;
    }

    /**
     * PUBLIC LOOKUP EXCEPTION: the firm and form are resolved from their public slugs.
     *
     * @return array<string, mixed>|null
     */
    public function findBySlug(string $firmaSlug, string $formSlug): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT f.*, fi.nombre AS firma_nombre, fi.slug AS firma_slug, fi.estado AS firma_estado
             FROM intake_forms f
             INNER JOIN firmas fi ON fi.id = f.firma_id
             WHERE fi.slug = :firma_slug AND f.slug = :form_slug
               AND fi.deleted_at IS NULL AND f.deleted_at IS NULL'
        );
        $statement->execute(['firma_slug' => $firmaSlug, 'form_slug' => $formSlug]);
        $row = $statement->fetch();

        return is_array($row) ? $row : null;
    }

    /**
     * PUBLIC LOOKUP EXCEPTION: used only after a public slug resolved the form id.
     *
     * @return array<string, mixed>|null
     */
    public function findPublicById(int $id): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT f.*, fi.nombre AS firma_nombre, fi.slug AS firma_slug, fi.estado AS firma_estado
             FROM intake_forms f
             INNER JOIN firmas fi ON fi.id = f.firma_id
             WHERE f.id = :id AND fi.deleted_at IS NULL AND f.deleted_at IS NULL'
        );
        $statement->execute(['id' => $id]);
        $row = $statement->fetch();

        return is_array($row) ? $row : null;
    }

    public function slugExists(string $slug, ?int $excludeId = null): bool
    {
        // PUBLIC SLUG EXCEPTION: slugs are globally unique by schema.
        $sql = 'SELECT COUNT(*) FROM intake_forms WHERE slug = :slug AND deleted_at IS NULL';
        $params = ['slug' => $slug];
        if ($excludeId !== null) {
            $sql .= ' AND id <> :id';
            $params['id'] = $excludeId;
        }
        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);

        return (int) $statement->fetchColumn() > 0;
    }

    /** @param array<string, mixed> $data */
    public function create(array $data): int
    {
        // TENANT FILTER: firma_id is mandatory in the inserted row.
        $statement = $this->pdo->prepare(
            'INSERT INTO intake_forms
                (firma_id, nombre, slug, titulo, descripcion, campos, activo, mensaje_exito,
                 notificar_emails, created_at, updated_at)
             VALUES
                (:firma_id, :nombre, :slug, :titulo, :descripcion, :campos, :activo, :mensaje_exito,
                 :notificar_emails, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)'
        );
        $statement->execute($data);

        return (int) $this->pdo->lastInsertId();
    }

    /** @param array<string, mixed> $data */
    public function update(int $id, int $firmaId, array $data): bool
    {
        // TENANT FILTER
        $statement = $this->pdo->prepare(
            'UPDATE intake_forms
             SET nombre = :nombre, titulo = :titulo, descripcion = :descripcion, campos = :campos,
                 activo = :activo, mensaje_exito = :mensaje_exito,
                 notificar_emails = :notificar_emails, updated_at = CURRENT_TIMESTAMP
             WHERE id = :id AND firma_id = :firma_id AND deleted_at IS NULL'
        );
        $statement->execute($data + ['id' => $id, 'firma_id' => $firmaId]);

        return $statement->rowCount() > 0;
    }

    public function softDelete(int $id, int $firmaId): bool
    {
        // TENANT FILTER
        $statement = $this->pdo->prepare(
            'UPDATE intake_forms
             SET deleted_at = CURRENT_TIMESTAMP, activo = 0, updated_at = CURRENT_TIMESTAMP
             WHERE id = :id AND firma_id = :firma_id AND deleted_at IS NULL'
        );
        $statement->execute(['id' => $id, 'firma_id' => $firmaId]);

        return $statement->rowCount() > 0;
    }

    /** @param array<string, mixed> $data */
    public function createSubmission(array $data): int
    {
        // TENANT FILTER: firma_id is mandatory in the inserted submission.
        $statement = $this->pdo->prepare(
            'INSERT INTO intake_submissions
                (intake_form_id, firma_id, datos, prospecto_id, ip_address, user_agent,
                 procesado, created_at, updated_at)
             VALUES
                (:intake_form_id, :firma_id, :datos, NULL, :ip_address, :user_agent,
                 0, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)'
        );
        $statement->execute($data);

        return (int) $this->pdo->lastInsertId();
    }

    public function markSubmissionProcessed(int $id, int $prospectoId, int $firmaId): bool
    {
        // TENANT FILTER
        $statement = $this->pdo->prepare(
            'UPDATE intake_submissions
             SET prospecto_id = :prospecto_id, procesado = 1, updated_at = CURRENT_TIMESTAMP
             WHERE id = :id AND firma_id = :firma_id AND procesado = 0'
        );
        $statement->execute([
            'prospecto_id' => $prospectoId,
            'id' => $id,
            'firma_id' => $firmaId,
        ]);

        return $statement->rowCount() > 0;
    }

    /** @return list<array<string, mixed>> */
    public function findSubmissions(int $formId, int $firmaId): array
    {
        // TENANT FILTER
        $statement = $this->pdo->prepare(
            'SELECT s.*, p.nombre AS prospecto_nombre, p.email AS prospecto_email,
                    p.telefono AS prospecto_telefono
             FROM intake_submissions s
             LEFT JOIN prospectos p ON p.id = s.prospecto_id AND p.firma_id = s.firma_id
             WHERE s.intake_form_id = :form_id AND s.firma_id = :firma_id
             ORDER BY s.created_at DESC, s.id DESC'
        );
        $statement->execute(['form_id' => $formId, 'firma_id' => $firmaId]);

        return $statement->fetchAll();
    }
}
