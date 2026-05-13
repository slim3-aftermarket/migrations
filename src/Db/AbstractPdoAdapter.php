<?php

declare(strict_types=1);

namespace Sl3Migrations\Db;

use PDO;
use PDOException;

abstract class AbstractPdoAdapter implements AdapterInterface
{
    public function __construct(
        protected readonly PDO $pdo,
    ) {
    }

    public function tableExists(string $tableName): bool
    {
        $statement = $this->pdo->prepare($this->tableExistsSql());
        if ($statement === false) {
            throw new DbException('Failed to prepare table existence query.');
        }

        $statement->execute([$tableName]);
        $result = $statement->fetchColumn();

        return $result !== false && (int) $result > 0;
    }

    public function ensureVersionTable(string $tableName): void
    {
        $this->execute(sprintf($this->versionTableSql(), $this->quoteIdentifier($tableName)));
    }

    public function fetchExecutedVersions(string $tableName): array
    {
        return $this->fetchAll(sprintf(
            'SELECT version, migration_name, executed_at, execution_time_ms, direction FROM %s ORDER BY version ASC',
            $this->quoteIdentifier($tableName)
        ));
    }

    public function logVersion(
        string $tableName,
        string $version,
        string $migrationName,
        string $direction,
        int $executionTimeMs
    ): void {
        $query = sprintf(
            'INSERT INTO %s (version, migration_name, executed_at, execution_time_ms, direction) VALUES (:version, :migration_name, :executed_at, :execution_time_ms, :direction)',
            $this->quoteIdentifier($tableName)
        );

        $statement = $this->pdo->prepare($query);
        if ($statement === false) {
            throw new DbException('Failed to prepare version insert query.');
        }

        $statement->execute([
            ':version' => $version,
            ':migration_name' => $migrationName,
            ':executed_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
            ':execution_time_ms' => $executionTimeMs,
            ':direction' => $direction,
        ]);
    }

    public function removeVersion(string $tableName, string $version): void
    {
        $query = sprintf(
            'DELETE FROM %s WHERE version = :version',
            $this->quoteIdentifier($tableName)
        );

        $statement = $this->pdo->prepare($query);
        if ($statement === false) {
            throw new DbException('Failed to prepare version delete query.');
        }

        $statement->execute([':version' => $version]);
    }

    public function execute(string $sql): void
    {
        try {
            $this->pdo->exec($sql);
        } catch (PDOException $exception) {
            throw new DbException($exception->getMessage(), (int) $exception->getCode(), $exception);
        }
    }

    public function fetchAll(string $sql): array
    {
        $statement = $this->pdo->query($sql);
        if ($statement === false) {
            return [];
        }

        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

        return is_array($rows) ? $rows : [];
    }

    abstract protected function tableExistsSql(): string;

    abstract protected function versionTableSql(): string;

    abstract protected function quoteIdentifier(string $name): string;
}
