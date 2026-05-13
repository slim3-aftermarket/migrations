<?php

declare(strict_types=1);

namespace Sl3Migrations\Migration;

final readonly class MigrationResult
{
    /**
     * @param list<string> $versions
     */
    public function __construct(
        public string $direction,
        public array $versions,
        public bool $dryRun = false,
    ) {
    }
}
