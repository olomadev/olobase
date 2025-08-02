<?php

declare(strict_types=1);

namespace Olobase\ModuleManager;

class Server
{
    private const MODULE_INSTALL_URL = 'https://olobase.dev/api/command/module/install';
    private const MODULE_REMOVE_URL = 'https://olobase.dev/api/command/module/remove';
    private const MODULE_DEPENDENCIES_URL = 'https://olobase.dev/api/command/module/findAllDependencies';

    public static function getModuleInstallUrl(): string
    {
        return self::MODULE_INSTALL_URL;
    }

    public static function getModuleRemoveUrl(): string
    {
        return self::MODULE_REMOVE_URL;
    }

    public static function getModuleDependenciesUrl(): string
    {
        return self::MODULE_DEPENDENCIES_URL;
    }
}
