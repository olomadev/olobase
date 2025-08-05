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

        putenv('APP_ENV=' . trim($env));

        try {
            $visited = [];
            $this->removeModule($module, $env, $output, $visited);

            $modulesConfigFile = APP_ROOT . '/config/module.config.php';
            $currentModules = (array)($this->config['modules'] ?? []);
            $newModules = array_diff($currentModules, $visited);

            if (empty($newModules)) {
                file_put_contents($modulesConfigFile, "<?php\n\nreturn [];\n");
            } else {
                file_put_contents($modulesConfigFile, "<?php\n\nreturn [\n    " . implode(",\n    ", array_map(fn ($m) => "'$m'", $newModules)) . "\n];\n");
            }

            $output->writeln("<info>Module $module removed successfully (with dependencies).</info>");
            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $output->writeln("<error>Error: {$e->getMessage()}</error>");
            return Command::FAILURE;
        }
    }

    private function removeModule(string $module, string $env, OutputInterface $output, array &$visited = [])
    {
        if (in_array($module, $visited, true)) {
            $output->writeln("<comment>Skipping already processed module: $module</comment>");
            return;
        }
        $visited[] = $module;

        // 1. Check if already installed in module.config.php
        $modulesConfig = (array)$this->config['modules'];
        if (! in_array($module, $modulesConfig, true)) {
            $output->writeln("<info>Module '$module' already removed. Skipping.</info>");
            return;
        }

        // 2. Read module composer.json
        $composerJsonPath = APP_ROOT . "/src/$module/composer.json";
        if (file_exists($composerJsonPath)) {
            $composerJson = json_decode(file_get_contents($composerJsonPath), true);
            $extra = $composerJson['extra'] ?? [];

            if (!empty($extra['olobase-module-dependencies']) && is_array($extra['olobase-module-dependencies'])) {
                foreach ($extra['olobase-module-dependencies'] as $dependency => $v) {
                    $output->writeln("<info>Removing dependency: $dependency (required by $module)</info>");
                    $this->removeModule($dependency, $env, $output, $visited);
                }
            }
        }

        // 3. Start module remove process
        $output->writeln("<info>Removing module: $module</info>");
        $this->performModuleRemove($module, $env, $output);
        return;
    }

    private function performModuleRemove(string $module, string $env, OutputInterface $output)
    {
        $modulesConfig = (array)$this->config['modules'];
        $key = array_search($module, $modulesConfig);
        unset($modulesConfig[$key]);

        $composerJsonFile = APP_ROOT . '/composer.json';
        $composerLockFile = APP_ROOT . '/composer.lock';
        $modulesConfigFile = APP_ROOT . '/config/module.config.php';

        // Mezzio deregister
        passthru("composer mezzio mezzio:module:deregister $module --ansi", $exitCode);

        if ($exitCode === 0) {
            $output->writeln("<info>[OK] Module $module unregistered via `mezzio:module:deregister`.</info>");
        } else {
            throw new RuntimeException(
                "[FAIL] Module $module could not be unregistered."
            );
        }
        $modulePath = "./src/$module";
        $moduleFullPath = APP_ROOT . "/$modulePath";
        $moduleJsonData = ComposerHelper::getComposerJsonData($moduleFullPath);
        $moduleFullName = $moduleJsonData['name'] ?? null;

        // Read the composer.json
        $composerJson = json_decode(file_get_contents($composerJsonFile), true);
        $repositories = $composerJson['repositories'] ?? [];
        $require = $composerJson['require'] ?? [];


        // Remove from repositories
        $repositories = array_filter(
            $repositories,
            fn ($repo) => !(
                isset($repo['url'])
                && (
                    $repo['url'] === "./src/$module"
                    || $repo['url'] === "src/$module"
                    || str_ends_with($repo['url'], "/$module")
                )
            )
        );
        $composerJson['repositories'] = array_values($repositories);

        $output->writeln("<comment>Module full name: " . ($moduleFullName ?? 'null') . "</comment>");

        // Remove from require
        if ($moduleFullName && isset($require[$moduleFullName])) {
            unset($require[$moduleFullName]);
            $composerJson['require'] = $require;

            // Run composer remove
            passthru("composer remove $moduleFullName --ansi", $returnVar);

            if ($returnVar === 0) {
                $output->writeln("<info>Module '{$moduleFullName}' removed successfully from composer.json.</info>");
            } else {
                throw new RuntimeException(
                    "Failed to remove module '{$moduleFullName}' from composer.json."
                );
            }
        }
        // Remove from autoload-dev psr-4
        $autoloadDevKey = $module . "\\Tests\\";

        // if the psr-4 part is present and it is indeed an associative array
        if (isset($composerJson['autoload-dev']['psr-4']) && is_array($composerJson['autoload-dev']['psr-4'])) {
            unset($composerJson['autoload-dev']['psr-4'][$autoloadDevKey]);

            $output->writeln("<info>Removed autoload-dev: $autoloadDevKey.</info>");
        }

        // We need to make sure that the empty autoload "psr-4" content is an object.
        if (empty($composerJson['autoload-dev']['psr-4'])) {
            $composerJson['autoload-dev']['psr-4'] = new \stdClass();
        }

        // Save updated composer.json
        file_put_contents($composerJsonFile, json_encode($composerJson, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        // Save updated module config
        if (empty($modulesConfig)) { // fixes cs fixer errors ..
            file_put_contents($modulesConfigFile, "<?php\n\nreturn [];\n");
        } else {
            file_put_contents($modulesConfigFile, "<?php\n\nreturn [\n    " . implode(",\n    ", array_map(fn ($m) => "'$m'", $modulesConfig)) . "\n];\n");
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

        $conn = DoctrineHelper::getConnection($this->config['db']);
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

        return Command::FAILURE;
    }

}
