<?php

declare(strict_types=1);

namespace Sl3Migrations\Migration;

use ReflectionMethod;
use Sl3Migrations\Db\AdapterInterface;
use Throwable;

final class MigrationManager
{
    public function __construct(
        private readonly AdapterInterface $adapter,
        private readonly MigrationRepository $repository,
        private readonly MigrationStateStore $stateStore,
        private readonly string $migrationsPath,
    ) {
    }

    public function stateStoreExists(): bool
    {
        return $this->stateStore->exists();
    }

    public function stateTableName(): string
    {
        return $this->stateStore->tableName();
    }

    public function initialize(): void
    {
        $this->stateStore->ensureInitialized();
    }

    public function migrate(?string $targetVersion = null, bool $dryRun = false): MigrationResult
    {
        $all = $this->repository->all($this->migrationsPath);
        $applied = $this->stateStore->appliedMap();
        $pending = array_values(array_filter(
            $all,
            static function (MigrationDefinition $migration) use ($applied, $targetVersion): bool {
                if (isset($applied[$migration->version])) {
                    return false;
                }

                if ($targetVersion !== null) {
                    return strcmp($migration->version, $targetVersion) <= 0;
                }

                return true;
            }
        ));

        $executed = [];
        foreach ($pending as $migrationDefinition) {
            $executed[] = $migrationDefinition->version;
            if ($dryRun) {
                continue;
            }

            $startedAt = microtime(true);
            $this->runMigrationWithAfterBody($migrationDefinition, 'up', function () use ($migrationDefinition, $startedAt): void {
                $elapsed = (int) round((microtime(true) - $startedAt) * 1000);
                $this->stateStore->markApplied(
                    $migrationDefinition->version,
                    $migrationDefinition->migrationName(),
                    $elapsed
                );
            });
        }

        return new MigrationResult('up', $executed, $dryRun);
    }

    public function rollback(?string $targetVersion = null, int $steps = 1, bool $dryRun = false): MigrationResult
    {
        $all = $this->repository->all($this->migrationsPath);
        $definitionsByVersion = [];
        foreach ($all as $definition) {
            $definitionsByVersion[$definition->version] = $definition;
        }

        $applied = array_keys($this->stateStore->appliedMap());
        rsort($applied, SORT_STRING);

        $toRollback = [];
        foreach ($applied as $version) {
            if ($targetVersion !== null) {
                if (strcmp($version, $targetVersion) <= 0) {
                    continue;
                }
            } elseif (count($toRollback) >= $steps) {
                break;
            }

            if (isset($definitionsByVersion[$version])) {
                $toRollback[] = $definitionsByVersion[$version];
            }
        }

        $executed = [];
        foreach ($toRollback as $migrationDefinition) {
            $executed[] = $migrationDefinition->version;
            if ($dryRun) {
                continue;
            }

            $this->runMigrationWithAfterBody($migrationDefinition, 'down', function () use ($migrationDefinition): void {
                $this->stateStore->markRolledBack($migrationDefinition->version);
            });
        }

        return new MigrationResult('down', $executed, $dryRun);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function status(): array
    {
        $all = $this->repository->all($this->migrationsPath);
        $applied = $this->stateStore->appliedMap();

        $rows = [];
        foreach ($all as $definition) {
            $appliedRow = $applied[$definition->version] ?? null;
            $rows[] = [
                'version' => $definition->version,
                'migration_name' => $definition->migrationName(),
                'status' => $appliedRow !== null ? 'up' : 'down',
                'executed_at' => $appliedRow['executed_at'] ?? null,
            ];
        }

        return $rows;
    }

    /**
     * Runs migration SQL and then $afterBody in one transaction when {@see AbstractMigration::isTransactional()} is true.
     *
     * @param callable():void $afterBody
     */
    private function runMigrationWithAfterBody(MigrationDefinition $definition, string $direction, callable $afterBody): void
    {
        $migration = $this->instantiateMigration($definition);
        $migration->setAdapter($this->adapter);
        $migration->setDirection($direction);

        $run = function () use ($migration, $definition, $direction, $afterBody): void {
            $this->invokeMigrationDirection($migration, $definition, $direction);
            $afterBody();
        };

        if (!$migration->isTransactional()) {
            $run();

            return;
        }

        $this->adapter->beginTransaction();
        try {
            $run();
            $this->adapter->commit();
        } catch (Throwable $exception) {
            $this->adapter->rollBack();
            throw $exception;
        }
    }

    private function instantiateMigration(MigrationDefinition $definition): AbstractMigration
    {
        $migration = new $definition->className();
        if (!$migration instanceof AbstractMigration) {
            throw new MigrationException(sprintf(
                'Class `%s` must extend %s.',
                $definition->className,
                AbstractMigration::class
            ));
        }

        return $migration;
    }

    private function invokeMigrationDirection(AbstractMigration $migration, MigrationDefinition $definition, string $direction): void
    {
        if ($direction === 'up') {
            if ($this->hasCustomMethod($migration, 'up')) {
                $migration->up();
                return;
            }

            if ($this->hasCustomMethod($migration, 'change')) {
                $migration->change();
                return;
            }

            throw new MigrationException(sprintf(
                'Migration `%s` must implement change() or up().',
                $definition->className
            ));
        }

        if ($this->hasCustomMethod($migration, 'down')) {
            $migration->down();
            return;
        }

        if ($this->hasCustomMethod($migration, 'change')) {
            $migration->change();
            return;
        }

        throw new MigrationException(sprintf(
            'Migration `%s` must implement down() or reversible change().',
            $definition->className
        ));
    }

    private function hasCustomMethod(AbstractMigration $migration, string $methodName): bool
    {
        $method = new ReflectionMethod($migration, $methodName);

        return $method->getDeclaringClass()->getName() !== AbstractMigration::class;
    }
}
