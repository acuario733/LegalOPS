<?php

declare(strict_types=1);

use App\Core\Config;
use App\Core\Database;

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
if (PHP_VERSION_ID < 80200) {
    fwrite(STDERR, "Se requiere PHP 8.2 o superior.\n");
    exit(1);
}

$basePath = dirname(__DIR__);
require $basePath . '/vendor/autoload.php';

$email = strtolower(trim((string) ($argv[1] ?? '')));
$password = (string) ($argv[2] ?? '');
$name = trim((string) ($argv[3] ?? 'Superadministrador'));
if (filter_var($email, FILTER_VALIDATE_EMAIL) === false || strlen($password) < 12 || mb_strlen($name) < 3) {
    fwrite(STDERR, "Uso: php scripts/create_superadmin.php correo contraseña-segura \"Nombre\"\n");
    exit(1);
}

try {
    Config::load($basePath);
    $pdo = (new Database((array) Config::get('database', [])))->connection();
    $scope = 'global:' . $email;
    $exists = $pdo->prepare('SELECT id FROM usuarios WHERE email_scope=:scope AND deleted_at IS NULL');
    $exists->execute(['scope' => $scope]);
    if ($exists->fetchColumn() !== false) {
        fwrite(STDERR, "Ya existe un superadministrador con ese correo.\n");
        exit(1);
    }
    $statement = $pdo->prepare(
        'INSERT INTO usuarios
        (firma_id,nombre,email,email_normalizado,email_scope,password_hash,tipo,estado,must_change_password,created_at,updated_at)
        VALUES (NULL,:nombre,:email,:email_normalizado,:scope,:password_hash,\'superadmin\',\'activo\',0,CURRENT_TIMESTAMP(6),CURRENT_TIMESTAMP(6))'
    );
    $statement->execute(['nombre' => $name, 'email' => $email, 'email_normalizado' => $email, 'scope' => $scope, 'password_hash' => password_hash($password, PASSWORD_DEFAULT)]);
    echo "Superadministrador creado correctamente.\n";
} catch (Throwable $exception) {
    fwrite(STDERR, "No fue posible crear el superadministrador. Revise la configuración y los logs.\n");
    exit(1);
}
