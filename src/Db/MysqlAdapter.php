<?php

declare(strict_types=1);

namespace Sl3Migrations\Db;

final class MysqlAdapter extends AbstractPdoAdapter
{
    protected function tableExistsSql(): string
    {
        return 'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?';
    }

    protected function versionTableSql(): string
    {
        return <<<SQL
CREATE TABLE IF NOT EXISTS %s (
    version VARCHAR(14) NOT NULL PRIMARY KEY,
    migration_name VARCHAR(255) NOT NULL,
    executed_at DATETIME NOT NULL,
    execution_time_ms INT NOT NULL,
    direction VARCHAR(10) NOT NULL
)
SQL;
    }

    protected function quoteIdentifier(string $name): string
    {
        return sprintf('`%s`', str_replace('`', '``', $name));
    }
}
