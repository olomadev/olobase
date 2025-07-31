<?php

declare(strict_types=1);

namespace Olobase\Router;

interface AttributeRouteProviderInterface
{
    public function registerRoutes(string $moduleDirectory): void;
}
