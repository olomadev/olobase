<?php

declare(strict_types=1);

namespace Olobase\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class Input
{
    public function __construct(
        public string $name,
        public bool $required = true,
        public array $filters = [],
        public array $validators = []
    ) {
    }
}
