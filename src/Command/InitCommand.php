<?php

declare(strict_types=1);

namespace Sl3Migrations\Command;

use RuntimeException;
use Sl3Migrations\Config\ConfigLoader;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class InitCommand extends AbstractMigrationCommand
{
    protected function configure(): void
    {
        $this
            ->setName('init')
            ->setDescription('Initialize migration config and db_version table.')
            ->setHelp(<<<'HELP'
Creates a default `sl3-migrations.php` and ensures `db_version` table exists.

Adapter configuration examples:

SQLite:
  return [
      'driver' => 'sqlite',
      'database' => '${DB_PATH:-./var/db.sqlite}',
      'migrations_path' => 'migrations',
      'version_table' => 'db_version',
  ];

MySQL/MariaDB:
  return [
      'driver' => 'mysql',
      'host' => '${DB_HOST:-127.0.0.1}',
      'port' => '${DB_PORT:-3306}',
      'database' => '${DB_NAME}',
      'username' => '${DB_USER}',
      'password' => '${DB_PASSWORD}',
      'charset' => 'utf8mb4',
      'migrations_path' => 'migrations',
      'version_table' => 'db_version',
  ];

PostgreSQL:
  return [
      'driver' => 'pgsql',
      'host' => '${DB_HOST:-127.0.0.1}',
      'port' => '${DB_PORT:-5432}',
      'database' => '${DB_NAME}',
      'username' => '${DB_USER}',
      'password' => '${DB_PASSWORD}',
      'migrations_path' => 'migrations',
      'version_table' => 'db_version',
  ];

You can provide env file explicitly:
  sl3-migrations init --env-file=.env

By default commands also try to read `.env` next to the configuration file.
HELP)
            ->addConfigOptions();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $configPath = $this->configurationPath($input) ?? (getcwd() . DIRECTORY_SEPARATOR . ConfigLoader::DEFAULT_CONFIG_FILE);

        if (!is_file($configPath)) {
            $template = <<<'PHP'
<?php

return [
    'driver' => 'sqlite',
    'database' => '${DB_PATH:-./var/db.sqlite}',
    'migrations_path' => 'migrations',
    'version_table' => 'db_version',
];
PHP;

            if (@file_put_contents($configPath, $template . PHP_EOL) === false) {
                throw new RuntimeException(sprintf('Failed to create config at `%s`.', $configPath));
            }

            $output->writeln(sprintf('<info>Created config: %s</info>', $configPath));
        }

        $config = (new ConfigLoader())->load($configPath, $this->envFilePath($input));
        if (!is_dir($config->migrationsPath)) {
            mkdir($config->migrationsPath, 0775, true);
            $output->writeln(sprintf('<info>Created migrations directory: %s</info>', $config->migrationsPath));
        }

        $manager = $this->buildManager($input);
        $manager->initialize();

        $output->writeln('<info>Initialization complete. Table `db_version` is ready.</info>');

        return self::SUCCESS;
    }
}
