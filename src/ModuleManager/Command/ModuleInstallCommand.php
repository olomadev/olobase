<?php

declare(strict_types=1);

namespace Olobase\ModuleManager\Command;

use RuntimeException;
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

    public function setConfig(array $config)
    {
        $this->config = $config;
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Install a module.')
            ->addOption('name', null, InputOption::VALUE_REQUIRED, 'Module name')
            ->addOption('env', null, InputOption::VALUE_REQUIRED, 'Environment')
            ->addOption('v', null, InputOption::VALUE_OPTIONAL, 'Version');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $module = trim((string)$input->getOption('name'));
        $env = $input->getOption('env');
        $version = $input->getOption('v');

        if (!$module || !$env) {
            $output->writeln('<error>--module and --env options are required.</error>');
            return Command::FAILURE;
        }
        putenv('APP_ENV='.trim($env));

        try {
            $visited = [];
            $this->installModule($module, $env, $output, $visited, $version);
            $output->writeln("<info>Module $module installed successfully (with dependencies).</info>");
            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $output->writeln("<error>Error: {$e->getMessage()}</error>");
            return Command::FAILURE;
        }

        $output->writeln("<info>Module $module installed successfully.</info>");
        return Command::SUCCESS;
    }

    private function installModule(string $module, string $env, OutputInterface $output, array &$visited = [], $version = null)
    {
        if (in_array($module, $visited, true)) {
            $output->writeln("<comment>Skipping already processed module: $module</comment>");
            return Command::SUCCESS;
        }
        $visited[] = $module;

        // 1. Check if already installed in module.config.php
        $modulesConfig = (array)$this->config['modules'];
        if (in_array($module, $modulesConfig, true)) {
            $output->writeln("<info>Module '$module' already installed. Skipping.</info>");
            return Command::SUCCESS;
        }

        // 2. Read module composer.json
        $composerJsonPath = APP_ROOT . "/src/$module/composer.json";
        if (file_exists($composerJsonPath)) {
            $composerJson = json_decode(file_get_contents($composerJsonPath), true);
            $extra = $composerJson['extra'] ?? [];

            if (!empty($extra['olobase-module-dependencies']) && is_array($extra['olobase-module-dependencies'])) {
                foreach ($extra['olobase-module-dependencies'] as $dependency => $v) {
                    $output->writeln("<info>Resolving dependency: $dependency (required by $module)</info>");
                    $this->installModule($dependency, $env, $output, $visited, $v);
                }
            }
        }

        // 3. Start module installation
        $output->writeln("<info>Installing module: $module</info>");
        $this->performModuleInstallation($module, $env, $output, $version);
    }

    private function performModuleInstallation(string $module, string $env, OutputInterface $output, $version = null): void
    {
        $composerJsonFile = APP_ROOT . '/composer.json';
        $composerLockFile = APP_ROOT . '/composer.lock';
        $modulesConfigFile = APP_ROOT . '/config/module.config.php';

        // Mezzio register
        passthru("composer mezzio mezzio:module:register $module --ansi", $exitCode);

        if ($exitCode === 0) {
            $output->writeln("<info>[OK] Module $module registered via `mezzio:module:register`.</info>");
        } else {
            throw new RuntimeException(
                "[FAIL] Module $module could not be registered using mezzio:module:register."
            );
        }

        // Read the composer.json
        $composerJson = json_decode(file_get_contents($composerJsonFile), true);
        $repositories = $composerJson['repositories'] ?? [];
        $require = $composerJson['require'] ?? [];

        $modulesConfig = (array)$this->config['modules'];
        $modulesConfig[] = $module;
        $output->writeln("<info>Installing module: $module.</info>");

        $modulePath = "./src/$module";
        $moduleFullPath = APP_ROOT . "/$modulePath";
        $moduleJsonData = ComposerHelper::getComposerJsonData($moduleFullPath);

        // Add to repositories
        $repositories[] = [
            'type' => 'path',
            'url' => $modulePath,
            'options' => ['symlink' => true],
        ];

        // Add to require
        if ($moduleJsonData && ! empty($moduleJsonData['name'])) {
            $require[$moduleJsonData['name']] = empty($moduleJsonData['version']) ? '*' : $moduleJsonData['version'];
        }

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

        // We need to make sure that the empty autoload "psr-4" content is an object.
        if (empty($composerJson['autoload-dev']['psr-4'])) {
            $composerJson['autoload-dev']['psr-4'] = new \stdClass();
        }
        file_put_contents($composerJsonFile, json_encode($composerJson, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        // Save updated module config
        file_put_contents($modulesConfigFile, "<?php\n\nreturn [\n    " . implode(",\n    ", array_map(fn ($m) => "'$m'", $modulesConfig)) . "\n];\n");

        // echo "\033[34mRunning composer dump-autoload...\n\033[0m";
        // passthru("composer dump-autoload");

        if (file_exists($composerLockFile)) {
            unlink($composerLockFile);
            $output->writeln("<info>File composer.lock removed successfully.</info>");
        }

        $return = ComposerHelper::runComposerInstall($output);
        if (false == $return) {
            throw new RuntimeException(
                'Aborting: Migration skipped due to composer install failure.'
            );
        }

        $conn = DoctrineHelper::getConnection($this->config['db']);
        $migration = new ModuleMigrationRunner($output);
        $migration->command('install');
        $return = $migration->run($module, $conn);

        if ($return) { // run composer scripts for current module ..

            $scripts = new ModuleComposerScriptRunner($output);
            $scripts->command('install');
            $scripts->run($moduleFullPath);
            return;

        } else {
            throw new RuntimeException(
                'Aborting: Migration failure.'
            );
            return;
        }
    }

}
