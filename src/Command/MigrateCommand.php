<?php

declare(strict_types=1);

namespace Sl3Migrations\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class MigrateCommand extends AbstractMigrationCommand
{
    protected function configure(): void
    {
        $this
            ->setName('migrate')
            ->setDescription('Run pending migrations.')
            ->addConfigOptions()
            ->addOption('target', null, InputOption::VALUE_OPTIONAL, 'Target version')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Show what will be migrated without applying changes')
            ->addOption('environment', null, InputOption::VALUE_OPTIONAL, 'Not supported in MVP');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if ($input->getOption('environment') !== null) {
            $output->writeln('<comment>`--environment` is not supported in MVP (single runtime config).</comment>');
        }

        $manager = $this->buildManager($input);
        if (!$this->ensureStateStoreReady($manager, $output)) {
            return self::FAILURE;
        }

        $target = $input->getOption('target');
        $dryRun = (bool) $input->getOption('dry-run');
        $result = $manager->migrate(is_string($target) ? $target : null, $dryRun);

        if ($result->versions === []) {
            $output->writeln('<info>No pending migrations.</info>');
            return self::SUCCESS;
        }

        $output->writeln(sprintf(
            '<info>%s %d migration(s): %s</info>',
            $dryRun ? 'Would apply' : 'Applied',
            count($result->versions),
            implode(', ', $result->versions)
        ));

        return self::SUCCESS;
    }
}
