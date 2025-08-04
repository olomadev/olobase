<?php

declare(strict_types=1);

namespace Olobase\ModuleManager;

use Symfony\Component\Console\Output\OutputInterface;

class ComposerHelper
{
    public static function runComposerInstall(OutputInterface $output): bool
    {
        $output->writeln("<info>Running composer install...</info>");

        passthru("composer install --ansi", $returnVar);

        if ($returnVar === 0) {
            $output->writeln("<info>Composer install completed successfully.</info>");
            return true;
        } else {
            $output->writeln("<error>Composer install failed with status code $returnVar.</error>");
            return false;
        }
    }

    public static function getComposerJsonData(string $moduleFullPath)
    {
        $composerFile = $moduleFullPath . '/composer.json';
        if (!file_exists($composerFile)) {
            return false;
        }
        $json = file_get_contents($composerFile);
        $data = json_decode($json, true);

        return is_array($data) ? $data : false;
    }

}
