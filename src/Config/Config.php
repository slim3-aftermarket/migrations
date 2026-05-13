<?php

declare(strict_types=1);

namespace Sl3Migrations\Config;

final readonly class Config
{
    public function __construct(
        public string $driver,
        public ?string $dsn,
        public ?string $host,
        public ?int $port,
        public ?string $database,
        public ?string $username,
        public ?string $password,
        public string $charset,
        public string $migrationsPath,
        public string $versionTable,
    ) {
    }
}
