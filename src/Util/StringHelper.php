<?php

declare(strict_types=1);

namespace Olobase\Util;

class StringHelper
{
    /**
     * Format snake case strings to camel case
     *
     * @param  string $string text
     * @return string
     */
    public static function snakeToCamel(string $string): string
    {
        $segments = explode('_', $string);
        return $segments[0] . implode('', array_map('ucfirst', array_slice($segments, 1)));
    }
}
