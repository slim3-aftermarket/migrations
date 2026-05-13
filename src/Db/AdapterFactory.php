<?php

declare(strict_types=1);

namespace Sl3Migrations\Db;

use PDO;
use Sl3Migrations\Config\Config;

final class AdapterFactory
{
    public function create(Config $config): AdapterInterface
    {
        $pdo = $this->createPdo($config);

        return match (strtolower($config->driver)) {
            'mysql', 'mariadb' => new MysqlAdapter($pdo),
            'pgsql', 'postgres', 'postgresql' => new PostgresAdapter($pdo),
            'sqlite', 'sqlite3' => new SqliteAdapter($pdo),
            default => throw new DbException(sprintf(
                'Unsupported driver `%s`. Supported drivers: mysql, pgsql, sqlite.',
                $config->driver
            )),
        };
    }

    private function createPdo(Config $config): PDO
    {
        $dsn = $config->dsn ?? $this->buildDsn($config);

        $pdo = new PDO(
            $dsn,
            $config->username,
            $config->password,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]
        );

        return $pdo;
    }

    private function buildDsn(Config $config): string
    {
        return match (strtolower($config->driver)) {
            'mysql', 'mariadb' => sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=%s',
                $config->host ?? '127.0.0.1',
                $config->port ?? 3306,
                $config->database ?? '',
                $config->charset
            ),
            'pgsql', 'postgres', 'postgresql' => sprintf(
                'pgsql:host=%s;port=%d;dbname=%s',
                $config->host ?? '127.0.0.1',
                $config->port ?? 5432,
                $config->database ?? ''
            ),
            'sqlite', 'sqlite3' => sprintf(
                'sqlite:%s',
                $config->database ?? ':memory:'
            ),
            default => throw new DbException(sprintf(
                'Unable to build DSN for unsupported driver `%s`.',
                $config->driver
            )),
        };
    }
}
