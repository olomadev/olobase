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

class ModuleInstallCommand extends Command
{
    protected $config = array();
    protected static $defaultName = 'module:install';
    
    public function setConfig(array $config)
    {
        $this->config = $config;
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Install a module.')
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

        if (!in_array($module, $modulesConfig)) {

            $composerJsonFile = APP_ROOT . '/composer.json';
            $composerLockFile = APP_ROOT . '/composer.lock';
            $modulesConfigFile = APP_ROOT . '/config/module.config.php';

            // Read the composer.json
            $composerJson = json_decode(file_get_contents($composerJsonFile), true);
            $repositories = $composerJson['repositories'] ?? [];
            $require = $composerJson['require'] ?? [];

            $modulesConfig[] = $module;
            $output->writeln("<info>Installing module: $module.</info>");

            $modulePath = "./src/$module";
            $moduleFullPath = APP_ROOT . "/$modulePath";
            $moduleFullName = ComposerHelper::getModuleNameFromComposerJson($moduleFullPath);

            // Add to repositories
            $repositories[] = [
                'type' => 'path',
                'url' => $modulePath,
                'options' => ['symlink' => true],
            ];

            // Add to require
            if ($moduleFullName) {
                $require[$moduleFullName] = '*';
            }

            // Add Tests/ folder to autoload-dev
            // if (!isset($composerJson['autoload-dev'])) {
            //     $composerJson['autoload-dev'] = ['psr-4' => []];
            // } elseif (!isset($composerJson['autoload-dev']['psr-4']) || !is_array($composerJson['autoload-dev']['psr-4'])
            //     || array_keys($composerJson['autoload-dev']['psr-4']) === range(0, count($composerJson['autoload-dev']['psr-4']) - 1)) {
            //     $composerJson['autoload-dev']['psr-4'] = []; // If the array is indexed (i.e. there is no object), it will be fixed
            // }

            // Add Tests/ folder to autoload-dev
            if (!isset($composerJson['autoload-dev'])) {
                $composerJson['autoload-dev'] = ['psr-4' => new \stdClass()];
            } elseif (!isset($composerJson['autoload-dev']['psr-4']) || !is_array($composerJson['autoload-dev']['psr-4'])
                || array_keys($composerJson['autoload-dev']['psr-4']) === range(0, count($composerJson['autoload-dev']['psr-4']) - 1)) {
                $composerJson['autoload-dev']['psr-4'] = new \stdClass();
            }

            $autoloadDevKey = $module . "\\Tests\\";
            $autoloadDevPath = "src/{$module}/Tests/";

            if (!is_object($composerJson['autoload-dev']['psr-4']) && !array_key_exists($autoloadDevKey, $composerJson['autoload-dev']['psr-4'])) {
                $composerJson['autoload-dev']['psr-4'][$autoloadDevKey] = $autoloadDevPath;
                $output->writeln("<info>Added autoload-dev: \"$autoloadDevKey\" => \"$autoloadDevPath\".</info>");
            }

            // Save updated composer.json
            $composerJson['repositories'] = $repositories;
            $composerJson['require'] = $require;
            file_put_contents($composerJsonFile, json_encode($composerJson, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            // Save updated module config
            file_put_contents($modulesConfigFile, "<?php\nreturn [\n    " . implode(",\n    ", array_map(fn ($m) => "'$m'", $modulesConfig)) . "\n];\n");

            // echo "\033[34mRunning composer dump-autoload...\n\033[0m";
            // passthru("composer dump-autoload");

            // Mezzio register
            passthru("composer mezzio mezzio:module:register $module --ansi", $exitCode);

            if ($exitCode === 0) {
                $output->writeln("<info>[OK] Module $module registered via `mezzio:module:register`.</info>");
            } else {
                $output->writeln("<error>[FAIL] Module $module could not be registered.</error>");
                return Command::FAILURE;
            }

            if (file_exists($composerLockFile)) {
                unlink($composerLockFile);
                $output->writeln("<info>composer.lock file removed successfully.</info>");
            }

            $return = ComposerHelper::runComposerInstall($output);
            if (false == $return) {
                $output->writeln("<error>Aborting: Migration skipped due to composer install failure.</error>");
                return Command::FAILURE;
            }

            $conn = self::getConnection($this->config['db']);
            $migration = new ModuleMigrationRunner($output);
            $migration->command('install');
            $return = $migration->run($module, $conn);

            if ($return) { // run composer scripts for current module ..

                $scripts = new ModuleComposerScriptRunner($output);
                $scripts->command('install');
                $scripts->run($moduleFullPath);

                return Command::SUCCESS;
            } else {
                return Command::FAILURE;
            }

        } else {
            $output->writeln("<info>Module '{$module}' is already enabled in module.config.php.</info>");
            return Command::SUCCESS;
        }

        $output->writeln("<info>Module $module installed successfully.</info>");
        return Command::SUCCESS;
    }

    private static function getConnection(array $laminasDbConfig): Connection
    {
        $doctrineDbConfig = DoctrineHelper::formatLaminasDbConfig($laminasDbConfig);
        $conn = DriverManager::getConnection($doctrineDbConfig);

        return $conn;
    }

}
