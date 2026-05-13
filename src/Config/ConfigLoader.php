<?php

declare(strict_types=1);

namespace Sl3Migrations\Config;

final class ConfigLoader
{
    public const DEFAULT_CONFIG_FILE = 'sl3-migrations.php';
    public const DEFAULT_ENV_FILE = '.env';

    private ?string $activeEnvFilePath = null;
    private bool $activeEnvFileProvidedExplicitly = false;
    private bool $activeEnvFileLoaded = false;

    public function load(?string $configPath = null, ?string $envFilePath = null): Config
    {
        $path = $configPath ?? getcwd() . DIRECTORY_SEPARATOR . self::DEFAULT_CONFIG_FILE;
        $absolutePath = $this->resolveAbsolutePath($path, getcwd());

        if (!is_file($absolutePath)) {
            throw new ConfigException(sprintf(
                'Configuration file `%s` not found. Run `sl3-migrations init` first.',
                $absolutePath
            ));
        }

        $resolvedEnvFilePath = $envFilePath;
        if ($resolvedEnvFilePath === null || $resolvedEnvFilePath === '') {
            $resolvedEnvFilePath = self::DEFAULT_ENV_FILE;
            $this->activeEnvFileProvidedExplicitly = false;
        } else {
            $this->activeEnvFileProvidedExplicitly = true;
        }
        $this->activeEnvFilePath = $this->resolveAbsolutePath($resolvedEnvFilePath, dirname($absolutePath));
        $this->activeEnvFileLoaded = $this->loadEnvFile($this->activeEnvFilePath, $this->activeEnvFileProvidedExplicitly);

        $rawConfig = require $absolutePath;

        if (!is_array($rawConfig)) {
            throw new ConfigException('Configuration file must return an array.');
        }

        /** @var array<string, mixed> $resolved */
        $resolved = $this->resolveEnvReferences($rawConfig);

        $driver = (string) ($resolved['driver'] ?? '');
        if ($driver === '') {
            throw new ConfigException('Configuration key `driver` is required.');
        }

        $versionTable = (string) ($resolved['version_table'] ?? 'db_version');
        if ($versionTable !== 'db_version') {
            throw new ConfigException('Only `db_version` table is supported for migration state in MVP.');
        }

        $migrationsPath = (string) ($resolved['migrations_path'] ?? 'migrations');
        if (!str_starts_with($migrationsPath, DIRECTORY_SEPARATOR)) {
            $migrationsPath = dirname($absolutePath) . DIRECTORY_SEPARATOR . $migrationsPath;
        }

        return new Config(
            driver: $driver,
            dsn: $this->nullableString($resolved['dsn'] ?? null),
            host: $this->nullableString($resolved['host'] ?? null),
            port: isset($resolved['port']) ? (int) $resolved['port'] : null,
            database: $this->nullableString($resolved['database'] ?? null),
            username: $this->nullableString($resolved['username'] ?? null),
            password: $this->nullableString($resolved['password'] ?? null),
            charset: (string) ($resolved['charset'] ?? 'utf8mb4'),
            migrationsPath: $migrationsPath,
            versionTable: $versionTable,
        );
    }

    /**
     * @param mixed $value
     * @return mixed
     */
    private function resolveEnvReferences(mixed $value): mixed
    {
        if (is_array($value)) {
            $resolved = [];
            foreach ($value as $key => $item) {
                $resolved[$key] = $this->resolveEnvReferences($item);
            }

            return $resolved;
        }

        if (!is_string($value)) {
            return $value;
        }

        return (string) preg_replace_callback(
            '/\$\{([A-Z0-9_]+)(?::-([^}]*))?\}/i',
            function (array $matches): string {
                $name = $matches[1];
                $default = $matches[2] ?? null;
                $envValue = getenv($name);

                if ($envValue === false) {
                    if ($default !== null) {
                        return $default;
                    }

                    throw new ConfigException($this->buildMissingEnvVariableMessage($name));
                }

                return $envValue;
            },
            $value
        );
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (string) $value;
    }

    private function resolveAbsolutePath(string $path, string $baseDirectory): string
    {
        if (str_starts_with($path, DIRECTORY_SEPARATOR)) {
            return $path;
        }

        return rtrim($baseDirectory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $path;
    }

    private function loadEnvFile(string $absolutePath, bool $required): bool
    {
        if (!is_file($absolutePath)) {
            if ($required) {
                throw new ConfigException(sprintf('Env file `%s` not found.', $absolutePath));
            }

            return false;
        }

        $lines = file($absolutePath, FILE_IGNORE_NEW_LINES);
        if ($lines === false) {
            throw new ConfigException(sprintf('Failed to read env file `%s`.', $absolutePath));
        }

        foreach ($lines as $line) {
            $trimmed = trim($line);
            if ($trimmed === '' || str_starts_with($trimmed, '#')) {
                continue;
            }

            if (!preg_match('/^(?:export\s+)?([A-Z0-9_]+)\s*=\s*(.*)$/i', $trimmed, $matches)) {
                continue;
            }

            $key = $matches[1];
            if (getenv($key) !== false) {
                continue;
            }

            $value = trim($matches[2]);
            if (
                strlen($value) >= 2
                && (
                    ($value[0] === '"' && $value[strlen($value) - 1] === '"')
                    || ($value[0] === "'" && $value[strlen($value) - 1] === "'")
                )
            ) {
                $quote = $value[0];
                $value = substr($value, 1, -1);
                if ($quote === '"') {
                    $value = stripcslashes($value);
                }
            }

            putenv($key . '=' . $value);
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }

        return true;
    }

    private function buildMissingEnvVariableMessage(string $name): string
    {
        if ($this->activeEnvFilePath === null) {
            return sprintf(
                'Environment variable `%s` is not set and no default value was provided.',
                $name
            );
        }

        if (!$this->activeEnvFileLoaded) {
            return sprintf(
                'Environment variable `%s` is not set, and env file `%s` was not found. Provide the variable in the OS environment or create the env file.',
                $name,
                $this->activeEnvFilePath
            );
        }

        return sprintf(
            'Environment variable `%s` is not set after loading env file `%s`. Add it to env/OS environment or provide a default `${%s:-value}`.',
            $name,
            $this->activeEnvFilePath,
            $name
        );
    }
}
