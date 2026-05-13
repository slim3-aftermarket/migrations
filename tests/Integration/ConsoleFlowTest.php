<?php

declare(strict_types=1);

namespace Sl3Migrations\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Sl3Migrations\Application;
use Symfony\Component\Console\Tester\ApplicationTester;

final class ConsoleFlowTest extends TestCase
{
    private string $tmpDir;
    private string $configPath;
    private string $dbPath;
    private string $migrationsPath;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/sl3-migrations-flow-' . uniqid('', true);
        $this->migrationsPath = $this->tmpDir . '/migrations';
        $this->dbPath = $this->tmpDir . '/app.sqlite';
        $this->configPath = $this->tmpDir . '/sl3-migrations.php';

        mkdir($this->tmpDir, 0775, true);
        file_put_contents($this->configPath, <<<PHP
<?php

return [
    'driver' => 'sqlite',
    'database' => '{$this->dbPath}',
    'migrations_path' => '{$this->migrationsPath}',
    'version_table' => 'db_version',
];
PHP
        );
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->tmpDir);
    }

    public function testMigrateStatusRollbackFlow(): void
    {
        $appTester = new ApplicationTester(new Application());

        self::assertSame(0, $appTester->run([
            'command' => 'init',
            '--configuration' => $this->configPath,
        ]));

        file_put_contents($this->migrationsPath . '/Version20220718170654.php', <<<'PHP'
<?php

declare(strict_types=1);

use Sl3Migrations\Migration\AbstractMigration;

final class Version20220718170654 extends AbstractMigration
{
    public function change(): void
    {
        $this->addSql(
            'CREATE TABLE users (id INTEGER PRIMARY KEY, email TEXT NOT NULL)',
            'DROP TABLE users'
        );
    }
}
PHP
        );

        self::assertSame(0, $appTester->run([
            'command' => 'migrate',
            '--configuration' => $this->configPath,
        ]));

        self::assertSame(0, $appTester->run([
            'command' => 'status',
            '--configuration' => $this->configPath,
            '--format' => 'json',
        ]));
        $statusOutput = $appTester->getDisplay();
        self::assertStringContainsString('"version": "20220718170654"', $statusOutput);
        self::assertStringContainsString('"status": "up"', $statusOutput);

        self::assertSame(0, $appTester->run([
            'command' => 'rollback',
            '--configuration' => $this->configPath,
            '--steps' => '1',
        ]));

        self::assertSame(0, $appTester->run([
            'command' => 'status',
            '--configuration' => $this->configPath,
            '--format' => 'json',
        ]));
        $rolledBackStatusOutput = $appTester->getDisplay();
        self::assertStringContainsString('"status": "down"', $rolledBackStatusOutput);
    }

    public function testMigrateWithoutInitCreatesVersionTableAndAppliesMigrations(): void
    {
        mkdir($this->migrationsPath, 0775, true);

        file_put_contents($this->migrationsPath . '/Version20220718170654.php', <<<'PHP'
<?php

declare(strict_types=1);

use Sl3Migrations\Migration\AbstractMigration;

final class Version20220718170654 extends AbstractMigration
{
    public function change(): void
    {
        $this->addSql(
            'CREATE TABLE users (id INTEGER PRIMARY KEY, email TEXT NOT NULL)',
            'DROP TABLE users'
        );
    }
}
PHP
        );

        $appTester = new ApplicationTester(new Application());

        self::assertSame(0, $appTester->run([
            'command' => 'migrate',
            '--configuration' => $this->configPath,
        ]));

        $display = $appTester->getDisplay();
        self::assertStringContainsString('State table `db_version` was not found; creating it.', $display);
        self::assertStringContainsString('Applied 1 migration(s): 20220718170654', $display);

        $pdo = new \PDO('sqlite:' . $this->dbPath);
        $tables = (int) $pdo->query("SELECT COUNT(*) FROM sqlite_master WHERE type = 'table' AND name IN ('db_version', 'users')")->fetchColumn();
        self::assertSame(2, $tables);
    }

    public function testCreateCommandGeneratesTimestampBasedMigrationClass(): void
    {
        $appTester = new ApplicationTester(new Application());

        self::assertSame(0, $appTester->run([
            'command' => 'create',
            '--configuration' => $this->configPath,
            'name' => 'create_users_table',
        ]));

        $files = glob($this->migrationsPath . '/Version*.php');
        self::assertIsArray($files);
        self::assertCount(1, $files);

        $filePath = $files[0];
        self::assertMatchesRegularExpression('/Version\d{14}\.php$/', (string) $filePath);

        $content = file_get_contents((string) $filePath);
        self::assertIsString($content);
        self::assertMatchesRegularExpression('/final class Version\d{14} extends AbstractMigration/', $content);
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
