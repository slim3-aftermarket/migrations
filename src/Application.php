<?php

declare(strict_types=1);

namespace Sl3Migrations;

use Sl3Migrations\Command\CreateCommand;
use Sl3Migrations\Command\InitCommand;
use Sl3Migrations\Command\MigrateCommand;
use Sl3Migrations\Command\RollbackCommand;
use Sl3Migrations\Command\StatusCommand;
use Symfony\Component\Console\Application as SymfonyApplication;

final class Application extends SymfonyApplication
{
    public function __construct()
    {
        parent::__construct('sl3-migrations', '0.1.0');

        $this->add(new InitCommand());
        $this->add(new CreateCommand());
        $this->add(new MigrateCommand());
        $this->add(new RollbackCommand());
        $this->add(new StatusCommand());
    }
}
