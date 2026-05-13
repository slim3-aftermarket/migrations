<?php

declare(strict_types=1);

namespace Sl3Migrations\Tests\Unit\Migration;

use PDO;
use PHPUnit\Framework\TestCase;
use Sl3Migrations\Db\SqliteAdapter;
use Sl3Migrations\Migration\MigrationManager;
use Sl3Migrations\Migration\MigrationRepository;
use Sl3Migrations\Migration\MigrationStateStore;

final class MigrationManagerTest extends TestCase
{
    private string $tmpDir;
    private string $dbPath;
    private string $migrationsPath;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/sl3-migrations-manager-' . uniqid('', true);
        $this->migrationsPath = $this->tmpDir . '/migrations';
        $this->dbPath = $this->tmpDir . '/db.sqlite';

        mkdir($this->migrationsPath, 0775, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->tmpDir);
    }

    public function testUsesChangeMethodForMigrateAndRollback(): void
    {
        file_put_contents($this->migrationsPath . '/Version20220718170654.php', <<<'PHP'
<?php

declare(strict_types=1);

use Sl3Migrations\Migration\AbstractMigration;

final class Version20220718170654 extends AbstractMigration
{
    public function change(): void
    {
        $this->addSql(
            'CREATE TABLE items (id INTEGER PRIMARY KEY, name TEXT NOT NULL)',
            'DROP TABLE items'
        );
    }
}
PHP
        );

        $manager = $this->buildManager();
        $manager->initialize();

        $upResult = $manager->migrate();
        self::assertSame(['20220718170654'], $upResult->versions);

        $statusAfterUp = $manager->status();
        self::assertSame('up', $statusAfterUp[0]['status']);

        $downResult = $manager->rollback(steps: 1);
        self::assertSame(['20220718170654'], $downResult->versions);

        $statusAfterRollback = $manager->status();
        self::assertSame('down', $statusAfterRollback[0]['status']);
    }

    public function testPrefersExplicitUpAndDownOverChange(): void
    {
        file_put_contents($this->migrationsPath . '/Version20220718170655.php', <<<'PHP'
<?php

declare(strict_types=1);

use Sl3Migrations\Migration\AbstractMigration;

final class Version20220718170655 extends AbstractMigration
{
    public function up(): void
    {
        $this->execute('CREATE TABLE marker (id INTEGER PRIMARY KEY)');
    }

    public function down(): void
    {
        $this->execute('DROP TABLE marker');
    }

    public function change(): void
    {
        throw new RuntimeException('change() should not be called when up()/down() are provided');
    }
}
PHP
        );

        $manager = $this->buildManager();
        $manager->initialize();
        $manager->migrate();

        $pdo = new PDO('sqlite:' . $this->dbPath);
        $existsUp = (int) $pdo->query("SELECT COUNT(*) FROM sqlite_master WHERE type='table' AND name='marker'")->fetchColumn();
        self::assertSame(1, $existsUp);

        $manager->rollback(steps: 1);
        $existsDown = (int) $pdo->query("SELECT COUNT(*) FROM sqlite_master WHERE type='table' AND name='marker'")->fetchColumn();
        self::assertSame(0, $existsDown);
    }

    private function buildManager(): MigrationManager
    {
        $pdo = new PDO('sqlite:' . $this->dbPath);
        $adapter = new SqliteAdapter($pdo);

        return new MigrationManager(
            adapter: $adapter,
            repository: new MigrationRepository(),
            stateStore: new MigrationStateStore($adapter, 'db_version'),
            migrationsPath: $this->migrationsPath,
        );
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
