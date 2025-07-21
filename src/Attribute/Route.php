<?php

declare(strict_types=1);

namespace Olobase\Attribute;

use Attribute;

#[\Attribute(\Attribute::TARGET_CLASS)]
class Route
{
    public array $meta;

    public function __construct(
        public string $path,
        public array $methods = ['GET'],
        public array $middlewares = [],
        array $meta = []
    ) {
        $this->meta = $meta;
    }
}