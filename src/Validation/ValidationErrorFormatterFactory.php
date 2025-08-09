<?php

declare(strict_types=1);

namespace Olobase\Validation;

use Laminas\ServiceManager\Factory\FactoryInterface;
use Psr\Container\ContainerInterface;

class ValidationErrorFormatterFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null)
    {
        return new ValidationErrorFormatter(
            [
                'response_key'   => 'data',
                'multiple_error' => true,
            ]
        );
    }
}
