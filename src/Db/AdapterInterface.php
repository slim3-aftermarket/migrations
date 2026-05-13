<?php

declare(strict_types=1);

namespace Sl3Migrations\Db;

interface AdapterInterface
{
    public function tableExists(string $tableName): bool;

    public function ensureVersionTable(string $tableName): void;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function fetchExecutedVersions(string $tableName): array;

    public function logVersion(
        string $tableName,
        string $version,
        string $migrationName,
        string $direction,
        int $executionTimeMs
    ): void;

    public function removeVersion(string $tableName, string $version): void;

    public function execute(string $sql): void;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function fetchAll(string $sql): array;
}
