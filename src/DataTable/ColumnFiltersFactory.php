<?php

declare(strict_types=1);

namespace Olobase\DataTable;

use Laminas\Db\Adapter\AdapterInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Psr\Container\ContainerInterface;

class ColumnFiltersFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null)
    {
        return new ColumnFilters(adapter: $container->get(AdapterInterface::class));
    }
}
