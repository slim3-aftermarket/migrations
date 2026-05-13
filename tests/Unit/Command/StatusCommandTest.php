<?php

declare(strict_types=1);

namespace Sl3Migrations\Tests\Unit\Command;

use PHPUnit\Framework\TestCase;
use Sl3Migrations\Command\StatusCommand;
use Symfony\Component\Console\Tester\CommandTester;

final class StatusCommandTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/sl3-migrations-status-' . uniqid('', true);
        mkdir($this->tmpDir, 0775, true);
        mkdir($this->tmpDir . '/migrations', 0775, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->tmpDir);
    }

    public function testCreatesDbVersionTableWhenMissingThenSucceeds(): void
    {
        $configPath = $this->tmpDir . '/sl3-migrations.php';
        $dbPath = $this->tmpDir . '/app.sqlite';
        file_put_contents($configPath, <<<PHP
<?php

return [
    'driver' => 'sqlite',
    'database' => '{$dbPath}',
    'migrations_path' => '{$this->tmpDir}/migrations',
    'version_table' => 'db_version',
];
PHP
        );

        $command = new StatusCommand();
        $tester = new CommandTester($command);
        $exitCode = $tester->execute(['--configuration' => $configPath]);
        $output = $tester->getDisplay();

        self::assertSame(0, $exitCode);
        self::assertStringContainsString('State table `db_version` was not found; creating it.', $output);
        self::assertStringContainsString('State table `db_version` is ready.', $output);

        $pdo = new \PDO('sqlite:' . $dbPath);
        $count = $pdo->query("SELECT COUNT(*) FROM sqlite_master WHERE type = 'table' AND name = 'db_version'")->fetchColumn();
        self::assertSame(1, (int) $count);
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
