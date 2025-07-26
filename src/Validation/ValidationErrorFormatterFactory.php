<?php

declare(strict_types=1);

namespace Olobase\Validation;

use Psr\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class ValidationErrorFormatterFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        return new ValidationErrorFormatter(
            [
                'response_key' => 'data',
                'multiple_error' => true,
            ]
        );
    }
}
