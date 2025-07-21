<?php

declare(strict_types=1);

namespace Olobase\DataTable;

use Laminas\Db\Adapter\AdapterInterface;
use Psr\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class ColumnFiltersFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null)
    {
        return new ColumnFilters($container->get(AdapterInterface::class));
    }
}
