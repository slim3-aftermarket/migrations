<?php

declare(strict_types=1);

namespace Sl3Migrations\Migration;

use Sl3Migrations\Db\AdapterInterface;

abstract class AbstractMigration
{
    private AdapterInterface $adapter;
    private string $direction = 'up';

    /**
     * When true (default), migration body and version-table update run in one DB transaction.
     * Set to false for operations that cannot run inside a transaction (e.g. PostgreSQL CONCURRENTLY).
     */
    protected bool $transactional = true;

    final public function setAdapter(AdapterInterface $adapter): void
    {
        $this->adapter = $adapter;
    }

    final public function setDirection(string $direction): void
    {
        $this->direction = $direction;
    }

    final public function isTransactional(): bool
    {
        return $this->transactional;
    }

    public function up(): void
    {
    }

    public function down(): void
    {
    }

    public function change(): void
    {
    }

    protected function execute(string $sql): void
    {
        $this->adapter->execute($sql);
    }

    protected function addSql(string $upSql, ?string $downSql = null): void
    {
        if ($this->direction === 'up') {
            $this->adapter->execute($upSql);
            return;
        }

        if ($downSql === null) {
            throw new MigrationException(sprintf(
                'Migration `%s` contains an irreversible operation. Implement explicit down() method.',
                static::class
            ));
        }

        $this->adapter->execute($downSql);
    }

    protected function createTable(string $tableName, string $columnsDefinition): void
    {
        $this->addSql(
            sprintf('CREATE TABLE %s (%s)', $tableName, $columnsDefinition),
            sprintf('DROP TABLE %s', $tableName),
        );
    }

    protected function dropTable(string $tableName): void
    {
        $this->addSql(
            sprintf('DROP TABLE %s', $tableName),
            null,
        );
    }

    protected function addColumn(string $tableName, string $columnDefinition): void
    {
        $columnName = trim(strtok($columnDefinition, ' '));

        $this->addSql(
            sprintf('ALTER TABLE %s ADD COLUMN %s', $tableName, $columnDefinition),
            sprintf('ALTER TABLE %s DROP COLUMN %s', $tableName, $columnName),
        );
    }

    protected function dropColumn(string $tableName, string $columnName): void
    {
        $this->addSql(
            sprintf('ALTER TABLE %s DROP COLUMN %s', $tableName, $columnName),
            null,
        );
    }

    protected function addIndex(string $tableName, string $indexName, string $columns): void
    {
        $this->addSql(
            sprintf('CREATE INDEX %s ON %s (%s)', $indexName, $tableName, $columns),
            sprintf('DROP INDEX %s', $indexName),
        );
    }

    protected function dropIndex(string $indexName): void
    {
        $this->addSql(
            sprintf('DROP INDEX %s', $indexName),
            null,
        );
    }
}
