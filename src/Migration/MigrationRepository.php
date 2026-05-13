<?php

declare(strict_types=1);

namespace Sl3Migrations\Migration;

final class MigrationRepository
{
    /**
     * @return list<MigrationDefinition>
     */
    public function all(string $migrationsPath): array
    {
        if (!is_dir($migrationsPath)) {
            return [];
        }

        $paths = glob(rtrim($migrationsPath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'Version*.php');
        if (!is_array($paths)) {
            return [];
        }

        $definitions = [];

        foreach ($paths as $path) {
            if (!preg_match('/Version(\d{14})\.php$/', $path, $matches)) {
                continue;
            }

            require_once $path;
            $realPath = realpath($path);
            $migrationClass = null;

            foreach (get_declared_classes() as $candidate) {
                if (!is_subclass_of($candidate, AbstractMigration::class)) {
                    continue;
                }

                $reflection = new \ReflectionClass($candidate);
                $candidatePath = $reflection->getFileName();
                if ($candidatePath !== false && $realPath !== false && realpath($candidatePath) === $realPath) {
                    $migrationClass = $candidate;
                    break;
                }
            }

            if ($migrationClass === null) {
                continue;
            }

            $definitions[] = new MigrationDefinition(
                version: $matches[1],
                className: $migrationClass,
                filePath: $path,
            );
        }

        usort(
            $definitions,
            static fn (MigrationDefinition $left, MigrationDefinition $right): int => strcmp($left->version, $right->version)
        );

        return $definitions;
    }
}
