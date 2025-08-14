<?php

declare(strict_types=1);

namespace Olobase\Authorization;

use Laminas\ServiceManager\Factory\FactoryInterface;
use Olobase\Authorization\PermissionRepositoryInterface;
use Psr\Container\ContainerInterface;

class AuthorizationFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null)
    {
        return new Authorization($container->get(PermissionRepositoryInterface::class));
    }
}
