<?php

declare(strict_types=1);

namespace Olobase\Authentication\Util;

use Laminas\ServiceManager\Factory\FactoryInterface;
use Psr\Container\ContainerInterface;

class TokenEncryptHelperFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null)
    {
        return new TokenEncryptHelper($container->get('config'));
    }
}
