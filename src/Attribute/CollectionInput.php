<?php

declare(strict_types=1);

namespace Olobase\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class CollectionInput
{
    /**
     * @param string $name field name
     * @param array $fields Subfields (Input definitions)
     */
    public function __construct(
        public string $name,
        public array $fields
    ) {}
}
