<?php

declare(strict_types=1);

namespace Olobase;

use Authorization\Repository\PermissionRepository;
use Authorization\Repository\RoleRepository;
use Mezzio\Application;
use Olobase\Authorization\PermissionRepositoryInterface;
use Olobase\Authorization\RoleRepositoryInterface;
use Olobase\Authorization\Repository\NullPermissionRepository;
use Olobase\Authorization\Repository\NullRoleRepository;
use Olobase\Router\AttributeRouteCollector;
use Olobase\Router\AttributeRouteProviderInterface;
use Psr\Container\ContainerInterface;

/**
 * @see ConfigInterface
 */
class ConfigProvider
{
    /**
     * Return oloma-dev configuration.
     *
     * @return array
     */
    public function __invoke()
    {
        return [
            'schema_mapper' => [
                'common_schema_module' => 'Common',
            ],
            'dependencies'  => $this->getDependencyConfig(),
        ];
    }

    /**
     * Return application-level dependency configuration.
     *
     * @return ServiceManagerConfigurationType
     */
    public function getDependencyConfig()
    {
        return [
            'factories' => [
                DataTable\ColumnFiltersInterface::class             => DataTable\ColumnFiltersFactory::class,
                Validation\ValidationErrorFormatterInterface::class => Validation\ValidationErrorFormatterFactory::class,
                AttributeRouteProviderInterface::class              => function (ContainerInterface $container) {
                    return new AttributeRouteCollector(
                        $container->get(Application::class),
                        $container
                    );
                },
                PermissionRepositoryInterface::class                => function ($container) {
                    if ($container->has(PermissionRepository::class)) {
                        return $container->get(PermissionRepository::class);
                    }
                    return new NullPermissionRepository();
                },
                RoleRepositoryInterface::class                      => function ($container) {
                    if ($container->has(RoleRepository::class)) {
                        return $container->get(RoleRepository::class);
                    }
                    return new NullRoleRepository();
                },
            ],
        ];
    }
}
