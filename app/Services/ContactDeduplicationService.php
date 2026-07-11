<?php

declare(strict_types=1);

namespace App\Services;

use PDO;

final class ContactDeduplicationService
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /** @return list<array{id: int, nombre: string, tipo: string}> */
    public function checkDuplicates(int $firmaId, ?string $email, ?string $documentHash): array
    {
        $email = strtolower(trim((string) $email));
        $clientWhere = [];
        $prospectWhere = [];
        $params = ['client_firma_id' => $firmaId, 'prospect_firma_id' => $firmaId];
        if ($email !== '') {
            $clientWhere[] = 'LOWER(email)=:client_email';
            $prospectWhere[] = 'LOWER(email)=:prospect_email';
            $params['client_email'] = $email;
            $params['prospect_email'] = $email;
        }
        if (trim((string) $documentHash) !== '') {
            $clientWhere[] = 'documento_hash=:client_documento_hash';
            $prospectWhere[] = 'documento_hash=:prospect_documento_hash';
            $params['client_documento_hash'] = $documentHash;
            $params['prospect_documento_hash'] = $documentHash;
        }
        if ($clientWhere === []) {
            return [];
        }
        $sql = 'SELECT id,nombre_razon_social AS nombre,\'cliente\' AS tipo FROM clientes
                WHERE firma_id=:client_firma_id AND deleted_at IS NULL AND (' . implode(' OR ', $clientWhere) . ')
                UNION ALL
                SELECT id,nombre,\'prospecto\' AS tipo FROM prospectos
                WHERE firma_id=:prospect_firma_id AND deleted_at IS NULL AND (' . implode(' OR ', $prospectWhere) . ')';
        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);

        return array_map(static fn (array $row): array => [
            'id' => (int) $row['id'],
            'nombre' => (string) $row['nombre'],
            'tipo' => (string) $row['tipo'],
        ], $statement->fetchAll());
    }

    public function documentHash(?string $document): ?string
    {
        $normalized = preg_replace('/[^A-Za-z0-9]+/', '', strtoupper(trim((string) $document))) ?? '';

        return $normalized === '' ? null : hash('sha256', mb_substr($normalized, 0, 80));
    }
}
