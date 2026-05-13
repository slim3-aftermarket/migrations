<?php

declare(strict_types=1);

namespace Sl3Migrations\Migration;

use Sl3Migrations\Db\AdapterInterface;

final class MigrationStateStore
{
    public function __construct(
        private readonly AdapterInterface $adapter,
        private readonly string $versionTable,
    ) {
    }

    public function tableName(): string
    {
        return $this->versionTable;
    }

    public function exists(): bool
    {
        return $this->adapter->tableExists($this->versionTable);
    }

    public function ensureInitialized(): void
    {
        $this->adapter->ensureVersionTable($this->versionTable);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function appliedMap(): array
    {
        $rows = $this->adapter->fetchExecutedVersions($this->versionTable);
        $map = [];
        foreach ($rows as $row) {
            $version = (string) ($row['version'] ?? '');
            if ($version !== '') {
                $map[$version] = $row;
            }
        }

        return $map;
    }

    public function markApplied(string $version, string $migrationName, int $executionTimeMs): void
    {
        $this->adapter->logVersion($this->versionTable, $version, $migrationName, 'up', $executionTimeMs);
    }

    public function markRolledBack(string $version): void
    {
        $this->adapter->removeVersion($this->versionTable, $version);
    }
}
