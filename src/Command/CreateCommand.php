<?php

declare(strict_types=1);

namespace Sl3Migrations\Command;

use RuntimeException;
use Sl3Migrations\Config\ConfigLoader;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class CreateCommand extends AbstractMigrationCommand
{
    protected function configure(): void
    {
        $this
            ->setName('create')
            ->setDescription('Create a new migration class with timestamp version.')
            ->addArgument('name', InputArgument::REQUIRED, 'Migration name, for example `create_users_table`')
            ->addConfigOptions();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $name = (string) $input->getArgument('name');
        $configPath = $this->configurationPath($input) ?? (getcwd() . DIRECTORY_SEPARATOR . ConfigLoader::DEFAULT_CONFIG_FILE);

        $migrationsPath = 'migrations';
        if (is_file($configPath)) {
            $config = (new ConfigLoader())->load($configPath, $this->envFilePath($input));
            $migrationsPath = $config->migrationsPath;
        }

        if (!is_dir($migrationsPath) && !mkdir($migrationsPath, 0775, true) && !is_dir($migrationsPath)) {
            throw new RuntimeException(sprintf('Failed to create migrations directory `%s`.', $migrationsPath));
        }

        $version = gmdate('YmdHis');
        $className = sprintf('Version%s', $version);
        $fileName = sprintf('%s/%s.php', rtrim($migrationsPath, '/'), $className);

        $template = <<<PHP
<?php

declare(strict_types=1);

use Sl3Migrations\Migration\AbstractMigration;

final class {$className} extends AbstractMigration
{
    public function change(): void
    {
        // {$name}
        // Example:
        // \$this->addSql(
        //     'CREATE TABLE users (id INTEGER PRIMARY KEY, email VARCHAR(255) NOT NULL)',
        //     'DROP TABLE users'
        // );
    }
}
PHP;

        if (@file_put_contents($fileName, $template . PHP_EOL) === false) {
            throw new RuntimeException(sprintf('Failed to write migration `%s`.', $fileName));
        }

        $output->writeln(sprintf('<info>Created migration: %s</info>', $fileName));

        return self::SUCCESS;
    }
}
