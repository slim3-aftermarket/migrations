<?php

declare(strict_types=1);

namespace Sl3Migrations\Command;

use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class StatusCommand extends AbstractMigrationCommand
{
    protected function configure(): void
    {
        $this
            ->setName('status')
            ->setDescription('Show migrations status.')
            ->addConfigOptions()
            ->addOption('format', null, InputOption::VALUE_OPTIONAL, 'Output format: text|json', 'text')
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

        $rows = $manager->status();
        $format = (string) $input->getOption('format');

        if ($format === 'json') {
            $output->writeln((string) json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            return self::SUCCESS;
        }

        if ($format !== 'text') {
            $output->writeln('<comment>Not yet supported in MVP: only `text` and `json` formats are available.</comment>');
            return self::FAILURE;
        }

        $table = new Table($output);
        $table->setHeaders(['Version', 'Migration', 'Status', 'Executed at']);
        foreach ($rows as $row) {
            $table->addRow([
                $row['version'],
                $row['migration_name'],
                $row['status'],
                $row['executed_at'] ?? '-',
            ]);
        }
        $table->render();

        return self::SUCCESS;
    }
}
