<?php

namespace Olobase\Router;

interface AttributeRouteProviderInterface
{
    public function registerRoutes(string $moduleDirectory): void;
}