<?php

declare(strict_types=1);

namespace Olobase\Authorization\Service;

use Psr\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Olobase\Authorization\Contract\PermissionModelInterface;

class AuthorizationFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null)
    {
        return new Authorization($container->get(PermissionModelInterface::class));
    }
}
