<?php

declare(strict_types=1);

namespace Olobase\ModuleManager\Command;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Olobase\ModuleManager\DoctrineHelper;
use Olobase\ModuleManager\ComposerHelper;
use Olobase\ModuleManager\ModuleMigrationRunner;
use Olobase\ModuleManager\ModuleComposerScriptRunner;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ModuleRemoveCommand extends Command
{
    protected $config = array();
    protected static $defaultName = 'module:remove';
    
    public function setConfig(array $config)
    {
        $this->config = $config;
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Remove a module.')
            ->addOption('name', null, InputOption::VALUE_REQUIRED, 'Module name')
            ->addOption('env', null, InputOption::VALUE_REQUIRED, 'Environment');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $module = trim((string)$input->getOption('name'));
        $env = $input->getOption('env');

        if (!$module || !$env) {
            $output->writeln('<error>--module and --env options are required.</error>');
            return Command::FAILURE;
        }
        putenv('APP_ENV='.trim($env));

        $modulesConfig = (array)$this->config['modules'];

        if (($key = array_search($module, $modulesConfig)) !== false) {
            unset($modulesConfig[$key]);

            $composerJsonFile = APP_ROOT . '/composer.json';
            $composerLockFile = APP_ROOT . '/composer.lock';
            $modulesConfigFile = APP_ROOT . '/config/module.config.php';

            $modulePath = "./src/$module";
            $moduleFullPath = APP_ROOT . "/$modulePath";
            $moduleFullName = ComposerHelper::getModuleNameFromComposerJson($moduleFullPath);

            // Read the composer.json
            $composerJson = json_decode(file_get_contents($composerJsonFile), true);
            $repositories = $composerJson['repositories'] ?? [];
            $require = $composerJson['require'] ?? [];

            // Remove from repositories
            $repositories = array_filter($repositories, fn ($repo) => !isset($repo['url']) || strpos($repo['url'], $module) === false);
            $composerJson['repositories'] = array_values($repositories);

            // Remove from require
            if ($moduleFullName && isset($require[$moduleFullName])) {
                unset($require[$moduleFullName]);
                $composerJson['require'] = $require;

                // Run composer remove
                passthru("composer remove $moduleFullName --ansi", $returnVar);

                if ($returnVar === 0) {
                    $output->writeln("<info>Module '{$moduleFullName}' removed successfully from composer.json.</info>");
                } else {
                    $output->writeln("<error>Failed to remove module '{$moduleFullName}' from composer.json.</error>");
                }
            }

            // Remove from autoload-dev psr-4
            $autoloadDevKey = $module . "\\Tests\\";

            // if the psr-4 part is present and it is indeed an associative array
        
            if (isset($composerJson['autoload-dev']['psr-4']) && is_array($composerJson['autoload-dev']['psr-4'])) {
                unset($composerJson['autoload-dev']['psr-4'][$autoloadDevKey]);
                
                $output->writeln("<info>Removed autoload-dev: $autoloadDevKey.</info>");
                
                // If psr-4 is empty, write {} as an empty object instead of []:
                if (empty($composerJson['autoload-dev']['psr-4'])) {
                    $composerJson['autoload-dev']['psr-4'] = new \stdClass();
                }
            }

            // if (
            //     isset($composerJson['autoload-dev']['psr-4']) &&
            //     is_array($composerJson['autoload-dev']['psr-4']) &&
            //     array_keys($composerJson['autoload-dev']['psr-4']) !== range(0, count($composerJson['autoload-dev']['psr-4']) - 1)
            // ) {
            //     if (isset($composerJson['autoload-dev']['psr-4'][$autoloadDevKey])) {
            //         unset($composerJson['autoload-dev']['psr-4'][$autoloadDevKey]);

            //         $output->writeln("<info>Removed autoload-dev: $autoloadDevKey.</info>");
            //     }
            // }

            // Save updated composer.json
            file_put_contents($composerJsonFile, json_encode($composerJson, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            // Save updated module config
            file_put_contents($modulesConfigFile, "<?php\nreturn [\n    " . implode(",\n    ", array_map(fn ($m) => "'$m'", $modulesConfig)) . "\n];\n");

            // Mezzio deregister
            passthru("composer mezzio mezzio:module:deregister $module --ansi", $exitCode);

            if ($exitCode === 0) {
                $output->writeln("<info>[OK] Module $module unregistered via `mezzio:module:deregister`.</info>");
            } else {
                $output->writeln("<error>[FAIL] Module $module could not be unregistered.</error>");
                return Command::FAILURE;
            }

            if (file_exists($composerLockFile)) {
                unlink($composerLockFile);
                $output->writeln("<info>composer.lock file removed successfully.</info>");
            }

            $return = ComposerHelper::runComposerInstall($output);
            if (false == $return) {
                $output->writeln("<error>Aborting: Rollback migration skipped due to composer remove failure.</error>");
                return Command::FAILURE;
            }
            // Running rollback migrations ...
            $output->writeln("<info>Running rollback migrations for module: $module</info>");

            $conn = self::getConnection($this->config['db']);
            $migration = new ModuleMigrationRunner($output);
            $migration->command('remove');
            $return = $migration->run($module, $conn);  // down migrations ..

            if ($return) { // run composer scripts for current module ..

                $scripts = new ModuleComposerScriptRunner($output);
                $scripts->command('remove');
                $scripts->run($moduleFullPath);

                $output->writeln("<info>Module $module removed successfully.</info>");

                return Command::SUCCESS;
            } else {
                return Command::FAILURE;
            }

        } else {
            $output->writeln("<error>Module '{$module}' is not found in module.config.php..</error>");
            return Command::FAILURE;
        }

        return Command::FAILURE;
    }

    private static function getConnection(array $laminasDbConfig): Connection
    {
        $doctrineDbConfig = DoctrineHelper::formatLaminasDbConfig($laminasDbConfig);
        $conn = DriverManager::getConnection($doctrineDbConfig);

        return $conn;
    }

}
