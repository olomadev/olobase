<?php

declare(strict_types=1);

namespace Olobase\Authentication\Helper;

use Psr\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class TokenEncryptHelperFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        return new TokenEncryptHelper($container->get('config'));
    }
}
