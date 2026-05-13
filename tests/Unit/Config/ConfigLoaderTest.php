<?php

declare(strict_types=1);

namespace Sl3Migrations\Tests\Unit\Config;

use PHPUnit\Framework\TestCase;
use Sl3Migrations\Config\ConfigException;
use Sl3Migrations\Config\ConfigLoader;

final class ConfigLoaderTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/sl3-migrations-tests-' . uniqid('', true);
        mkdir($this->tmpDir, 0775, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->tmpDir);
    }

    public function testResolvesEnvironmentVariables(): void
    {
        putenv('SL3_TEST_DB=' . $this->tmpDir . '/db.sqlite');
        putenv('SL3_TEST_DB_PASSWORD=secret');

        $configPath = $this->tmpDir . '/sl3-migrations.php';
        file_put_contents($configPath, <<<'PHP'
<?php

return [
    'driver' => 'sqlite',
    'database' => '${SL3_TEST_DB}',
    'password' => '${SL3_TEST_DB_PASSWORD}',
    'migrations_path' => 'migrations',
];
PHP
        );

        $config = (new ConfigLoader())->load($configPath);

        self::assertSame($this->tmpDir . '/db.sqlite', $config->database);
        self::assertSame('secret', $config->password);
        self::assertSame($this->tmpDir . '/migrations', $config->migrationsPath);
        self::assertSame('db_version', $config->versionTable);
    }

    public function testUsesDefaultValueWhenEnvironmentVariableMissing(): void
    {
        putenv('SL3_MISSING_KEY');

        $configPath = $this->tmpDir . '/sl3-migrations.php';
        file_put_contents($configPath, <<<'PHP'
<?php

return [
    'driver' => 'sqlite',
    'database' => '${SL3_MISSING_KEY:-default.sqlite}',
];
PHP
        );

        $config = (new ConfigLoader())->load($configPath);

        self::assertSame('default.sqlite', $config->database);
    }

    public function testThrowsWhenEnvironmentVariableMissingWithoutDefault(): void
    {
        putenv('SL3_MISSING_REQUIRED');

        $configPath = $this->tmpDir . '/sl3-migrations.php';
        file_put_contents($configPath, <<<'PHP'
<?php

return [
    'driver' => 'sqlite',
    'database' => '${SL3_MISSING_REQUIRED}',
];
PHP
        );

        $this->expectException(ConfigException::class);
        $this->expectExceptionMessage('Environment variable `SL3_MISSING_REQUIRED` is not set, and env file');
        (new ConfigLoader())->load($configPath);
    }

    public function testThrowsWhenVersionTableIsNotDbVersion(): void
    {
        $configPath = $this->tmpDir . '/sl3-migrations.php';
        file_put_contents($configPath, <<<'PHP'
<?php

return [
    'driver' => 'sqlite',
    'database' => ':memory:',
    'version_table' => 'custom_versions',
];
PHP
        );

        $this->expectException(ConfigException::class);
        $this->expectExceptionMessage('Only `db_version` table is supported');
        (new ConfigLoader())->load($configPath);
    }

    public function testLoadsVariablesFromEnvFile(): void
    {
        putenv('SL3_ENV_FILE_DB');
        putenv('SL3_ENV_FILE_PASSWORD');

        $envPath = $this->tmpDir . '/.env';
        file_put_contents($envPath, <<<ENV
SL3_ENV_FILE_DB=from_env_file.sqlite
SL3_ENV_FILE_PASSWORD=env_file_secret
ENV
        );

        $configPath = $this->tmpDir . '/sl3-migrations.php';
        file_put_contents($configPath, <<<'PHP'
<?php

return [
    'driver' => 'sqlite',
    'database' => '${SL3_ENV_FILE_DB}',
    'password' => '${SL3_ENV_FILE_PASSWORD}',
];
PHP
        );

        $config = (new ConfigLoader())->load($configPath, '.env');

        self::assertSame('from_env_file.sqlite', $config->database);
        self::assertSame('env_file_secret', $config->password);
    }

    public function testLoadsDefaultDotEnvWithoutExplicitOption(): void
    {
        putenv('SL3_DEFAULT_ENV_DB');

        $envPath = $this->tmpDir . '/.env';
        file_put_contents($envPath, 'SL3_DEFAULT_ENV_DB=default_env.sqlite');

        $configPath = $this->tmpDir . '/sl3-migrations.php';
        file_put_contents($configPath, <<<'PHP'
<?php

return [
    'driver' => 'sqlite',
    'database' => '${SL3_DEFAULT_ENV_DB}',
];
PHP
        );

        $config = (new ConfigLoader())->load($configPath);
        self::assertSame('default_env.sqlite', $config->database);
    }

    public function testThrowsWhenEnvFileIsMissing(): void
    {
        $configPath = $this->tmpDir . '/sl3-migrations.php';
        file_put_contents($configPath, <<<'PHP'
<?php

return [
    'driver' => 'sqlite',
    'database' => ':memory:',
];
PHP
        );

        $this->expectException(ConfigException::class);
        $this->expectExceptionMessage('Env file');
        (new ConfigLoader())->load($configPath, '.missing.env');
    }

    private function removeDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $items = scandir($directory);
        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $directory . DIRECTORY_SEPARATOR . $item;
            if (is_dir($path)) {
                $this->removeDirectory($path);
                continue;
            }

            unlink($path);
        }

        rmdir($directory);
    }
}
