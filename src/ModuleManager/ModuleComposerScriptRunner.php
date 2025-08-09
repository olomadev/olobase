<?php

declare(strict_types=1);

namespace Olobase\ModuleManager;

use Symfony\Component\Console\Output\OutputInterface;

use function file_exists;
use function file_get_contents;
use function json_decode;
use function passthru;

/**
 * Runs the "olobase-post-install-cmd" and "remove" scripts defined in the composer.json module.
 *
 * "extra": {
 *    "olobase-post-install-cmd": [
 *        "php ./src/Authentication/scripts/install/authentication-adapter.php",
 *        "php ./src/Authentication/scripts/install/authentication-config.php"
 *    ],
 *    "olobase-post-remove-cmd": [
 *        "php ./src/Authentication/scripts/remove/authentication-remove.php"
 *    ]
 * }
 */
class ModuleComposerScriptRunner
{
    private $command;

    public function __construct(private OutputInterface $output)
    {
    }

    public function command($command)
    {
        $this->command = $command;
    }

    public function run(string $moduleFullPath)
    {
        $moduleComposerPath = "$moduleFullPath/composer.json";

        if (! file_exists($moduleComposerPath)) {
            return;
        }

        $moduleComposer = json_decode(file_get_contents($moduleComposerPath), true);

        if ($this->command == "install" && ! empty($moduleComposer['extra']['olobase-post-install-cmd'])) {
            $postInstallCmds = $moduleComposer['extra']['olobase-post-install-cmd'] ?? [];
            foreach ($postInstallCmds as $cmd) {
                $this->output->writeln("<info>Running module post-install-cmd: $cmd.</info>");
                passthru($cmd . " --ansi");
            }
        }
        if ($this->command == "remove" && ! empty($moduleComposer['extra']['olobase-post-remove-cmd'])) {
            $postRemoveCmds = $moduleComposer['extra']['olobase-post-remove-cmd'] ?? [];
            foreach ($postRemoveCmds as $cmd) {
                $this->output->writeln("<info>Running module post-remove-cmd: $cmd.</info>");
                passthru($cmd . " --ansi");
            }
        }
    }
}
