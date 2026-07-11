<?php

declare(strict_types=1);

use App\Core\App;
use App\Services\StorageService;

require __DIR__ . '/../vendor/autoload.php';

$app = App::bootstrap(dirname(__DIR__));
$pdo = $app->container()->get(\PDO::class);
$storage = $app->container()->get(StorageService::class);
$statement = $pdo->query(
    'SELECT v.* FROM documento_versiones v
     WHERE v.s3_key IS NULL AND v.storage_path IS NOT NULL
     ORDER BY v.firma_id,v.documento_id,v.version_numero'
);
$migrated = 0;
$skipped = 0;
foreach ($statement->fetchAll() as $version) {
    $relative = str_replace('\\', '/', (string) $version['storage_path']);
    if ($relative === '' || str_contains($relative, '..') || str_starts_with($relative, '/')) {
        $skipped++;
        continue;
    }
    $path = dirname(__DIR__) . '/storage/' . $relative;
    if (!is_file($path) || hash_file('sha256', $path) !== (string) $version['checksum_sha256']) {
        $skipped++;
        continue;
    }
    $contents = file_get_contents($path);
    if (!is_string($contents)) {
        $skipped++;
        continue;
    }
    $safeName = strtolower((string) preg_replace('/[^a-zA-Z0-9._-]+/', '-', (string) $version['nombre_original']));
    $key = $storage->upload(
        (int) $version['firma_id'],
        'docs/' . (int) $version['documento_id'] . '/v' . (int) $version['version_numero']
            . '/' . (int) $version['documento_id'] . '_v' . (int) $version['version_numero'] . '_' . time() . '_' . trim($safeName, '-'),
        $contents,
        (string) $version['mime_detectado']
    );
    $update = $pdo->prepare(
        'UPDATE documento_versiones SET s3_key=:s3_key,s3_bucket=:s3_bucket
         WHERE id=:id AND firma_id=:firma_id AND s3_key IS NULL'
    );
    $update->execute([
        's3_key' => $key,
        's3_bucket' => (string) \App\Core\Config::env('S3_BUCKET', ''),
        'id' => (int) $version['id'],
        'firma_id' => (int) $version['firma_id'],
    ]);
    $migrated += $update->rowCount();
}

fwrite(STDOUT, sprintf("Migrados: %d; omitidos: %d\n", $migrated, $skipped));
