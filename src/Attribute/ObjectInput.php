<?php

declare(strict_types=1);

namespace Olobase\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class ObjectInput
{
    /**
     * @param string $name Field name for nested input.
     * @param array $fields Each field should be like:
     *   [
     *     'name' => 'id',
     *     'required' => true,
     *     'filters' => [],
     *     'validators' => []
     *   ]
     */
    public function __construct(
        public string $name,
        public array $fields
    ) {}
}
