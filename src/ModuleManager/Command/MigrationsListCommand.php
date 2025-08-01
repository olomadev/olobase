<?php

declare(strict_types=1);

namespace Olobase\ModuleManager\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class MigrationsListCommand extends Command
{
    protected static $defaultName = 'migrations:list';

    protected function configure(): void
    {
        $this
            ->setDescription('List migration status for a specific module')
            ->addOption('module', null, InputOption::VALUE_REQUIRED, 'Module name')
            ->addOption('env', null, InputOption::VALUE_REQUIRED, 'Environment', 'local');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $module = trim($input->getOption('module') ?? '');
        $env    = $input->getOption('env');

        if (!$module || !$env) {
            $output->writeln('<error>--module and --env options are required.</error>');
            return Command::FAILURE;
        }
        putenv('APP_ENV='.trim($env));

        passthru("php bin/migrations.php --module={$module} migrations:list --no-interaction --ansi");
    }
}
