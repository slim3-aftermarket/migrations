<?php

declare(strict_types=1);

namespace Sl3Migrations\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class RollbackCommand extends AbstractMigrationCommand
{
    protected function configure(): void
    {
        $this
            ->setName('rollback')
            ->setDescription('Rollback executed migrations.')
            ->addConfigOptions()
            ->addOption('target', null, InputOption::VALUE_OPTIONAL, 'Rollback down to this version (exclusive)')
            ->addOption('steps', null, InputOption::VALUE_OPTIONAL, 'How many latest migrations to rollback', '1')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Show what will be rolled back without applying changes')
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
        $steps = (int) $input->getOption('steps');
        $dryRun = (bool) $input->getOption('dry-run');

        $result = $manager->rollback(
            is_string($target) ? $target : null,
            $steps > 0 ? $steps : 1,
            $dryRun
        );

        if ($result->versions === []) {
            $output->writeln('<info>No migrations to rollback.</info>');
            return self::SUCCESS;
        }

        $output->writeln(sprintf(
            '<info>%s %d migration(s): %s</info>',
            $dryRun ? 'Would rollback' : 'Rolled back',
            count($result->versions),
            implode(', ', $result->versions)
        ));

        return self::SUCCESS;
    }
}
