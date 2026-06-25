<?php

declare(strict_types=1);

namespace App\Core;

use Closure;
use PDO;
use PDOException;
use RuntimeException;
use Throwable;

final class Database
{
    private ?PDO $connection = null;

    /** @param array<string, mixed> $config */
    public function __construct(private readonly array $config)
    {
    }

    public function connection(): PDO
    {
        if ($this->connection instanceof PDO) {
            return $this->connection;
        }

        $driver = (string) ($this->config['driver'] ?? 'mysql');
        if ($driver !== 'mysql') {
            throw new RuntimeException('El controlador de base de datos configurado no es compatible.');
        }

        $charset = (string) ($this->config['charset'] ?? 'utf8mb4');
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            (string) ($this->config['host'] ?? '127.0.0.1'),
            (int) ($this->config['port'] ?? 3306),
            (string) ($this->config['database'] ?? ''),
            $charset
        );

        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_STRINGIFY_FETCHES => false,
        ];

        if (defined('PDO::MYSQL_ATTR_INIT_COMMAND')) {
            $options[PDO::MYSQL_ATTR_INIT_COMMAND] = 'SET NAMES ' . $charset;
        }

        try {
            $this->connection = new PDO(
                $dsn,
                (string) ($this->config['username'] ?? ''),
                (string) ($this->config['password'] ?? ''),
                $options
            );
        } catch (PDOException $exception) {
            throw new RuntimeException('No fue posible establecer la conexión con la base de datos.', 0, $exception);
        }

        return $this->connection;
    }

    public function beginTransaction(): void
    {
        if (!$this->connection()->inTransaction()) {
            $this->connection()->beginTransaction();
        }
    }

    public function commit(): void
    {
        if ($this->connection()->inTransaction()) {
            $this->connection()->commit();
        }
    }

    public function rollBack(): void
    {
        if ($this->connection()->inTransaction()) {
            $this->connection()->rollBack();
        }
    }

    public function transaction(callable $callback): mixed
    {
        $pdo = $this->connection();
        if ($pdo->inTransaction()) {
            return $callback($pdo);
        }

        $pdo->beginTransaction();
        try {
            $result = Closure::fromCallable($callback)($pdo);
            $pdo->commit();

            return $result;
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $exception;
        }
    }
}

