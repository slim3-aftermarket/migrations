<?php

declare(strict_types=1);

namespace Sl3Migrations\Migration;

final readonly class MigrationDefinition
{
    public function __construct(
        public string $version,
        public string $className,
        public string $filePath,
    ) {
    }

    public function migrationName(): string
    {
        return basename($this->filePath);
    }
}
