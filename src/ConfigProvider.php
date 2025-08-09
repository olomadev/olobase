<?php

declare(strict_types=1);

namespace Olobase;

use Authorization\Model\PermissionModel;
use Authorization\Model\RoleModel;
use Mezzio\Application;
use Olobase\Authorization\Contract\PermissionModelInterface;
use Olobase\Authorization\Contract\RoleModelInterface;
use Olobase\Authorization\Model\NullPermissionModel;
use Olobase\Authorization\Model\NullRoleModel;
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
                PermissionModelInterface::class                     => function ($container) {
                    if ($container->has(PermissionModel::class)) {
                        return $container->has(PermissionModel::class);
                    }
                    return new NullPermissionModel();
                },
                RoleModelInterface::class                           => function ($container) {
                    if ($container->has(RoleModel::class)) {
                        return $container->get(RoleModel::class);
                    }
                    return new NullRoleModel();
                },
            ],
        ];
    }
}
