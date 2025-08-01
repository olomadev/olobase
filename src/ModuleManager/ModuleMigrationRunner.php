<?php

declare(strict_types=1);

namespace Olobase\ModuleManager;

use Doctrine\DBAL\Connection;
use Doctrine\Migrations\Version\Version;
use Doctrine\Migrations\DependencyFactory;
use Symfony\Component\Console\Output\OutputInterface;
use Doctrine\Migrations\Configuration\Connection\ExistingConnection;

class ModuleMigrationRunner
{
    private $command;

    public function __construct(private OutputInterface $output)
    {
    }

    public function command($command)
    {
        $this->command = $command;
    }

    public function run(
        string $module,
        Connection $conn,
        $prev = false,
        ?string $toVersion = null,
        $strict = false
    ): bool {

        $moduleMigrations = APP_ROOT . "/src/$module/src/Migrations";
        $migrationFiles = glob($moduleMigrations . '/Version*.php');

        $this->output->writeln("<info>Running migrations for module: $module ...</info>");

        if (!is_dir($moduleMigrations) || !$migrationFiles || count($migrationFiles) === 0) {
            $this->output->writeln("<info>Migrations skipped due to no found any migration file for: $module.</info>");
            return true; // no migration files found in /Migrations folder
        }
        $factory = self::createDependencyFactory($module, $moduleMigrations, $conn);

        if ($this->command == 'remove') {
            $executed = $factory->getMetadataStorage()->getExecutedMigrations();
            $executedMigrations = (array)$executed->getItems();
            $executedVersions = [];
            foreach ($executedMigrations as $migrationItem) {
                $version = $migrationItem->getVersion(); // Doctrine\Migrations\Version\Version
                $versionString = (string) $version; // e.g.: "Modules\Migrations\Version20250707151000"
                $executedVersions[] = $versionString;
            }
            $namespace = $module . '\\Migrations';
            $executedVersions = array_reverse($executedVersions); // Reverse order (newest first)
            $stepsToRollback = 0;
            foreach ($executedVersions as $version) {
                $stepsToRollback++;
            }
            if ($stepsToRollback === 0) {
                $this->output->writeln("<info>[OK] Already at or before target version.</info>");
                return true;
            }
            $this->output->writeln("<info>Rolling back $stepsToRollback step(s) to reach version: $toVersion.</info>");

            $return = true;
            for ($i = 0; $i < $stepsToRollback; $i++) {

                $this->output->writeln("<info>Rolling back step " . ($i + 1).".</info>");
                passthru("php bin/migrations.php --module={$module} migrations:migrate prev --no-interaction --ansi", $exitCode);

                if ($exitCode !== 0) {
                    $return = false;
                    $this->output->writeln("<info>Rollback failed at step " . ($i + 1).".</info>");
                    break;
                }
            }

            if ($return) {
                $this->output->writeln("<info>[OK] Rollback completed to version: $toVersion.</info>");
            }
            return $return;
        }

        if ($prev) {
            $return = true;
            $this->output->writeln("<info>Rolling back to previous version ...</info>");
            passthru("php bin/migrations.php --module={$module} migrations:migrate prev --no-interaction --ansi", $exitCode);

            if ($exitCode !== 0) {
                $return = false;
                $this->output->writeln("<error>Rollback failed</error>");
            }
            $this->output->writeln("<info>[OK] Rollback completed for module: $module.</info>");
            return $return;
        }

        if ($toVersion) {  // migrate to specific version ..
            $executed = $factory->getMetadataStorage()->getExecutedMigrations();
            $executedMigrations = (array)$executed->getItems();
            $executedVersions = [];
            foreach ($executedMigrations as $migrationItem) {
                $version = $migrationItem->getVersion(); // Doctrine\Migrations\Version\Version
                $versionString = (string) $version; // e.g.: "Modules\Migrations\Version20250707151000"
                $executedVersions[] = $versionString;
            }
            $namespace = $module . '\\Migrations';
            if (!str_starts_with($toVersion, $namespace)) {
                $fullToVersion = $namespace . '\\' . $toVersion;
            } else {
                $fullToVersion = $toVersion;
            }
            $toVersionObj = new Version($fullToVersion);
            $executedVersions = array_reverse($executedVersions); // Reverse order (newest first)

            $stepsToRollback = 0;
            foreach ($executedVersions as $version) {
                $stepsToRollback++;
                if ($version === (string)$toVersionObj) {
                    if (!$strict) {
                        $stepsToRollback--;
                    }
                }
            }
            if ($stepsToRollback === 0) {
                $this->output->writeln("<info>[OK] Already at or before target version.</info>");
                return true;
            }
            $this->output->writeln("<info>Rolling back $stepsToRollback step(s) to reach version: $toVersion.</info>");

            $return = true;
            for ($i = 0; $i < $stepsToRollback; $i++) {
                $this->output->writeln("<info>Rolling back step " . ($i + 1) . ".</info>");
                
                passthru("php bin/migrations.php --module={$module} migrations:migrate prev --no-interaction --ansi", $exitCode);

                if ($exitCode !== 0) {
                    $this->output->writeln("<error>Rollback failed at step " . ($i + 1).".</error>");
                    $return = false;
                    break;
                }
            }
            $this->output->writeln("<info>[OK] Rollback completed to version: $toVersion.</info>");            
            return $return;

        } else { // run migrations ...

            passthru("php bin/migrations.php --module={$module} migrations:migrate --no-interaction --ansi", $exitCode);

            if ($exitCode !== 0) {
                $this->output->writeln("<error>Migration failed</error>");
                return false;
            }
            $this->output->writeln("<info>[OK] Migration completed for module: $module.</info>");
        }

        return true;
    }

    public static function createDependencyFactory(string $module, string $migrationsPath, Connection $conn): DependencyFactory
    {
        $config = DoctrineHelper::createMigrationConfig($module);

        return DependencyFactory::fromConnection($config, new ExistingConnection($conn));
    }


}
