<?php

declare(strict_types=1);

namespace Olobase\Validation;

use Laminas\InputFilter\InputFilterInterface;

interface ValidationErrorFormatterInterface
{
    public function format(InputFilterInterface $filter): array;
}
