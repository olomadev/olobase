<?php

declare(strict_types=1);

namespace Olobase\ModuleManager\Command;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Olobase\ModuleManager\DoctrineHelper;
use Olobase\ModuleManager\ModuleMigrationRunner;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use function putenv;
use function trim;

class MigrationsMigrateCommand extends Command
{
    protected static $defaultName = 'migrations:migrate';

    protected function configure(): void
    {
        $this
            ->setDescription('Run module-based Doctrine migrations')
            ->addOption('module', null, InputOption::VALUE_REQUIRED, 'Module name')
            ->addOption('prev', null, InputOption::VALUE_NONE, 'Rollback previous migration')
            ->addOption('to', null, InputOption::VALUE_REQUIRED, 'Rollback to specific version')
            ->addOption('strict', null, InputOption::VALUE_NONE, 'Include target version rollback')
            ->addOption('env', null, InputOption::VALUE_REQUIRED, 'Environment name', 'local');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $module = trim($input->getOption('module') ?? '');
        $to     = $input->getOption('to') ?? null;
        $strict = $input->getOption('strict') ?? false;
        $prev   = $input->getOption('prev') ?? null;
        $env    = $input->getOption('env');

        if (! $module || ! $env) {
            $output->writeln('<error>--module and --env options are required.</error>');
            return Command::FAILURE;
        }
        putenv('APP_ENV=' . trim($env));

        $conn = self::getConnection($container->get('config')['db']);

        $migration = new ModuleMigrationRunner($output);
        $migration->run($module, $conn, false, $prev, $to, $strict);
    }

    private static function getConnection(array $laminasDbConfig): Connection
    {
        $doctrineDbConfig = DoctrineHelper::convertLaminasToDoctrineDbConfig($laminasDbConfig);
        return DriverManager::getConnection($doctrineDbConfig);
    }
}
