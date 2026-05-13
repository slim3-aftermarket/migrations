<?php

declare(strict_types=1);

namespace Sl3Migrations\Command;

use Sl3Migrations\Config\ConfigLoader;
use Sl3Migrations\Db\AdapterFactory;
use Sl3Migrations\Migration\MigrationManager;
use Sl3Migrations\Migration\MigrationRepository;
use Sl3Migrations\Migration\MigrationStateStore;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

abstract class AbstractMigrationCommand extends Command
{
    protected function buildManager(InputInterface $input): MigrationManager
    {
        $configLoader = new ConfigLoader();
        $config = $configLoader->load($this->configurationPath($input), $this->envFilePath($input));
        $adapter = (new AdapterFactory())->create($config);

        return new MigrationManager(
            adapter: $adapter,
            repository: new MigrationRepository(),
            stateStore: new MigrationStateStore($adapter, $config->versionTable),
            migrationsPath: $config->migrationsPath,
        );
    }

    protected function configurationPath(InputInterface $input): ?string
    {
        $path = $input->getOption('configuration');
        if (!is_string($path) || $path === '') {
            return null;
        }

        return $path;
    }

    protected function envFilePath(InputInterface $input): ?string
    {
        $path = $input->getOption('env-file');
        if (!is_string($path) || $path === '') {
            return null;
        }

        return $path;
    }

    protected function addConfigOptions(): static
    {
        $this->addOption('configuration', null, InputOption::VALUE_OPTIONAL, 'Path to config file');
        $this->addOption('env-file', null, InputOption::VALUE_OPTIONAL, 'Path to env file (.env)');

        return $this;
    }

    protected function ensureStateStoreReady(
        MigrationManager $manager,
        OutputInterface $output,
    ): bool {
        if ($manager->stateStoreExists()) {
            return true;
        }

        $table = $manager->stateTableName();
        $output->writeln(sprintf(
            '<comment>State table `%s` was not found; creating it.</comment>',
            $table
        ));
        $manager->initialize();
        $output->writeln(sprintf('<info>State table `%s` is ready.</info>', $table));

        return true;
    }
}
