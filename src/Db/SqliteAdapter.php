<?php

declare(strict_types=1);

namespace Sl3Migrations\Db;

final class SqliteAdapter extends AbstractPdoAdapter
{
    protected function tableExistsSql(): string
    {
        return "SELECT COUNT(*) FROM sqlite_master WHERE type = 'table' AND name = ?";
    }

    protected function versionTableSql(): string
    {
        return <<<SQL
CREATE TABLE IF NOT EXISTS %s (
    version TEXT NOT NULL PRIMARY KEY,
    migration_name TEXT NOT NULL,
    executed_at TEXT NOT NULL,
    execution_time_ms INTEGER NOT NULL,
    direction TEXT NOT NULL
)
SQL;
    }

    protected function quoteIdentifier(string $name): string
    {
        return sprintf('"%s"', str_replace('"', '""', $name));
    }
}
