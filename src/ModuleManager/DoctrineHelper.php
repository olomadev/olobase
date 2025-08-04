<?php

declare(strict_types=1);

namespace Olobase\ModuleManager;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\Migrations\Configuration\Migration\ConfigurationArray;

class DoctrineHelper
{
    public static function createMigrationConfig(string $module): ConfigurationArray
    {
        $migrationDir = APP_ROOT . '/src/' . $module . '/src/Migrations';
        $namespace    = $module . '\\Migrations';

        return new ConfigurationArray([
            'migrations_paths' => [
                $namespace => $migrationDir,
            ],
            'table_storage' => [
                'table_name' => 'migrations',
            ],
            'all_or_nothing' => true,
            'check_database_platform' => true,
        ]);
    }

    public static function convertLaminasToDoctrineDbConfig(array $laminasConfig): array
    {
        return [
            'driver'        => strtolower($laminasConfig['driver']),
            'host'          => $laminasConfig['hostname'] ?? '127.0.0.1',
            'user'          => $laminasConfig['username'] ?? 'root',
            'password'      => $laminasConfig['password'] ?? '',
            'dbname'        => $laminasConfig['database'] ?? '',
            'charset'       => 'utf8mb4',
            'driverOptions' => $laminasConfig['driver_options'] ?? [],
        ];
    }

    public static function getConnection(array $laminasDbConfig): Connection
    {
        $doctrineDbConfig = self::convertLaminasToDoctrineDbConfig($laminasDbConfig);
        $conn = DriverManager::getConnection($doctrineDbConfig);

        return $conn;
    }

}
