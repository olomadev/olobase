<?php

declare(strict_types=1);

namespace Olobase\ModuleManager;

class Server
{
    public static function getModuleInstallUrl(): string
    {
        return 'https://olobase.dev/api/command/module/install';
    }

    public static function getModuleRemoveUrl(): string
    {
        return 'https://olobase.dev/api/command/module/remove';
    }

    public static function getModuleDependenciesUrl(): string
    {
        return 'https://olobase.dev/api/command/module/findAllDependencies';
    }
}
