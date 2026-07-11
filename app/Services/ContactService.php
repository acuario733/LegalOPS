<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\HttpException;
use PDO;

final class ContactService
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /** @return list<array<string, mixed>> */
    public function search(int $firmId, string $query): array
    {
        $q = '%' . $this->normalize($query) . '%';
        // TENANT FILTER: firma_id = ?
        $statement = $this->pdo->prepare(
            'SELECT id,firma_id,type,display_name,email,phone,source_type,source_id
             FROM contacts
             WHERE firma_id=:firma_id AND deleted_at IS NULL
               AND (display_name_normalizado LIKE :q OR email LIKE :email)
             ORDER BY display_name ASC
             LIMIT 25'
        );
        $statement->execute(['firma_id' => $firmId, 'q' => $q, 'email' => '%' . trim($query) . '%']);

        return $statement->fetchAll();
    }

    /** @return array<string, mixed> */
    public function getById(int $firmId, int $contactId): array
    {
        // TENANT FILTER: firma_id = ?
        $statement = $this->pdo->prepare('SELECT * FROM contacts WHERE id=:id AND firma_id=:firma_id AND deleted_at IS NULL');
        $statement->execute(['id' => $contactId, 'firma_id' => $firmId]);
        $row = $statement->fetch();

        return is_array($row) ? $row : throw new HttpException(404, 'El contacto no existe en la firma.');
    }

    /** @return array<string, list<array<string, mixed>>> */
    public function getTimeline360(int $firmId, int $contactId): array
    {
        $contact = $this->getById($firmId, $contactId);
        $sourceType = (string) $contact['source_type'];
        $sourceId = (int) $contact['source_id'];
        $timeline = ['casos' => [], 'honorarios' => [], 'documentos' => [], 'comunicaciones' => []];

        if ($sourceType === 'cliente') {
            // TENANT FILTER: firma_id = ?
            $casos = $this->pdo->prepare('SELECT id,titulo,estado,created_at FROM casos WHERE firma_id=:firma_id AND cliente_id=:cliente_id AND deleted_at IS NULL ORDER BY created_at DESC');
            $casos->execute(['firma_id' => $firmId, 'cliente_id' => $sourceId]);
            $timeline['casos'] = $casos->fetchAll();

            // TENANT FILTER: firma_id = ?
            $honorarios = $this->pdo->prepare('SELECT id,concepto,monto,estado,created_at FROM honorarios WHERE firma_id=:firma_id AND cliente_id=:cliente_id AND deleted_at IS NULL ORDER BY created_at DESC');
            $honorarios->execute(['firma_id' => $firmId, 'cliente_id' => $sourceId]);
            $timeline['honorarios'] = $honorarios->fetchAll();
        }

        // TENANT FILTER: firma_id = ?
        $comunicaciones = $this->pdo->prepare('SELECT id,caso_id,tipo,asunto,fecha_comunicacion FROM caso_comunicaciones WHERE firma_id=:firma_id AND contact_id=:contact_id AND deleted_at IS NULL ORDER BY fecha_comunicacion DESC');
        $comunicaciones->execute(['firma_id' => $firmId, 'contact_id' => $contactId]);
        $timeline['comunicaciones'] = $comunicaciones->fetchAll();

        return $timeline;
    }

    private function normalize(string $value): string
    {
        return mb_substr(preg_replace('/\s+/', ' ', mb_strtolower(trim($value))) ?? '', 0, 180);
    }
}
