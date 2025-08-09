<?php

declare(strict_types=1);

namespace Olobase\ModuleManager\Command;

use Olobase\ModuleManager\ComposerHelper;
use Olobase\ModuleManager\DoctrineHelper;
use Olobase\ModuleManager\ModuleComposerScriptRunner;
use Olobase\ModuleManager\ModuleMigrationRunner;
use RuntimeException;
use stdClass;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

use function array_key_exists;
use function array_map;
use function array_merge;
use function array_unique;
use function array_values;
use function file_exists;
use function file_get_contents;
use function file_put_contents;
use function implode;
use function in_array;
use function is_array;
use function is_object;
use function json_decode;
use function json_encode;
use function passthru;
use function putenv;
use function trim;
use function unlink;

use const JSON_PRETTY_PRINT;
use const JSON_UNESCAPED_SLASHES;

class ModuleInstallCommand extends Command
{
    protected $config             = [];
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
            ->addOption('env', null, InputOption::VALUE_REQUIRED, 'Environment')
            ->addOption('v', null, InputOption::VALUE_OPTIONAL, 'Version');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $module  = trim((string) $input->getOption('name'));
        $env     = $input->getOption('env');
        $version = $input->getOption('v');

        if (! $module || ! $env) {
            $output->writeln('<error>--module and --env options are required.</error>');
            return Command::FAILURE;
        }
        putenv('APP_ENV=' . trim($env));

        try {
            $visited = [];
            $this->installModule($module, $env, $output, $visited, $version);
            $output->writeln("<info>Module $module installed successfully (with dependencies).</info>");
            return Command::SUCCESS;
        } catch (Throwable $e) {
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
            return;
        }
        $visited[] = $module;

        // 1. Check if already installed in module.config.php
        $modulesConfig = (array) $this->config['modules'];
        if (in_array($module, $modulesConfig, true)) {
            $output->writeln("<info>Module '$module' already installed. Skipping.</info>");
            return;
        }

        // 2. Read module composer.json
        $composerJsonPath = APP_ROOT . "/src/$module/composer.json";
        if (file_exists($composerJsonPath)) {
            $composerJson = json_decode(file_get_contents($composerJsonPath), true);
            $extra        = $composerJson['extra'] ?? [];

            if (! empty($extra['olobase-module-dependencies']) && is_array($extra['olobase-module-dependencies'])) {
                foreach ($extra['olobase-module-dependencies'] as $dependency => $v) {
                    $output->writeln("<info>Resolving dependency: $dependency (required by $module)</info>");
                    $this->installModule($dependency, $env, $output, $visited, $v);
                }
            }
        }

        // 3. Start module installation
        $output->writeln("<info>Installing module: $module</info>");
        $this->performModuleInstallation($module, $env, $output, $version);
        return;
    }

    private function performModuleInstallation(string $module, string $env, OutputInterface $output, $version = null): void
    {
        $composerJsonFile  = APP_ROOT . '/composer.json';
        $composerLockFile  = APP_ROOT . '/composer.lock';
        $modulesConfigFile = APP_ROOT . '/config/module.config.php';
        $modulePath        = "./src/$module";
        $moduleFullPath    = APP_ROOT . "/$modulePath";

        // 1. mezzio register
        passthru("composer mezzio mezzio:module:register $module --ansi", $exitCode);
        if ($exitCode === 0) {
            $output->writeln("<info>[OK] Module $module registered via `mezzio:module:register`.</info>");
        } else {
            throw new RuntimeException("[FAIL] Module $module could not be registered using mezzio:module:register.");
        }

        // 2. install composer.json
        $composerJson = json_decode(file_get_contents($composerJsonFile), true);
        $repositories = $composerJson['repositories'] ?? [];
        $require      = $composerJson['require'] ?? [];

        // 3. keep composer.json data
        $moduleJsonData = ComposerHelper::getComposerJsonData($moduleFullPath);

        // 4. add repositories
        $repositories[] = [
            'type'    => 'path',
            'url'     => $modulePath,
            'options' => ['symlink' => true],
        ];

        // 5. add require
        if ($moduleJsonData && ! empty($moduleJsonData['name'])) {
            $require[$moduleJsonData['name']] = empty($moduleJsonData['version']) ? '*' : $moduleJsonData['version'];
        }

        // 6. set autoload-dev
        if (! isset($composerJson['autoload-dev'])) {
            $composerJson['autoload-dev'] = ['psr-4' => new stdClass()];
        } elseif (! isset($composerJson['autoload-dev']['psr-4']) || ! is_array($composerJson['autoload-dev']['psr-4'])) {
            $composerJson['autoload-dev']['psr-4'] = new stdClass();
        }

        $autoloadDevKey  = $module . "\\Tests\\";
        $autoloadDevPath = "src/{$module}/Tests/";
        if (! is_object($composerJson['autoload-dev']['psr-4']) && ! array_key_exists($autoloadDevKey, $composerJson['autoload-dev']['psr-4'])) {
            $composerJson['autoload-dev']['psr-4'][$autoloadDevKey] = $autoloadDevPath;
            $output->writeln("<info>Added autoload-dev: \"$autoloadDevKey\" => \"$autoloadDevPath\".</info>");
        }

        // 7. save composer.json
        $composerJson['repositories'] = $repositories;
        $composerJson['require']      = $require;
        if (empty($composerJson['autoload-dev']['psr-4'])) {
            $composerJson['autoload-dev']['psr-4'] = new stdClass();
        }
        file_put_contents($composerJsonFile, json_encode($composerJson, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        // 8. update module.config.php
        if (file_exists($modulesConfigFile)) {
            $existingModules = include $modulesConfigFile;
            if (! is_array($existingModules)) {
                $existingModules = [];
            }
        } else {
            $existingModules = [];
        }

        $modulesConfig = array_values(array_unique(array_merge($existingModules, [$module])));
        file_put_contents(
            $modulesConfigFile,
            "<?php\n\nreturn [\n    " . implode(",\n    ", array_map(fn($m) => "'$m'", $modulesConfig)) . "\n];\n"
        );

        // 9. delete composer.lock
        if (file_exists($composerLockFile)) {
            unlink($composerLockFile);
            $output->writeln("<info>File composer.lock removed successfully.</info>");
        }

        // 10. composer install
        if (! ComposerHelper::runComposerInstall($output)) {
            throw new RuntimeException('Aborting: Migration skipped due to composer install failure.');
        }

        // 11. run migrations
        $conn      = DoctrineHelper::getConnection($this->config['db']);
        $migration = new ModuleMigrationRunner($output);
        $migration->command('install');
        if (! $migration->run($module, $conn)) {
            throw new RuntimeException('Aborting: Migration failure.');
        }

        // 12. run module composer script
        $scripts = new ModuleComposerScriptRunner($output);
        $scripts->command('install');
        $scripts->run($moduleFullPath);
    }
}
