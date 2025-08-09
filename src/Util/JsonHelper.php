<?php

declare(strict_types=1);

namespace Olobase\Util;

use Olobase\Exception\JsonDecodeException;

use function is_string;
use function json_decode;
use function json_encode;
use function json_last_error;
use function sprintf;
use function strpos;
use function trim;

use const JSON_ERROR_CTRL_CHAR;
use const JSON_ERROR_DEPTH;
use const JSON_ERROR_INF_OR_NAN;
use const JSON_ERROR_NONE;
use const JSON_ERROR_RECURSION;
use const JSON_ERROR_SYNTAX;
use const JSON_ERROR_UNSUPPORTED_TYPE;
use const JSON_ERROR_UTF8;
use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;

class JsonHelper
{
    /**
     * Debuggable json decode
     *
     * @param  string $data data
     * @return mixed
     * @throws JsonDecodeException
     */
    public static function jsonDecode(string $data)
    {
        if (empty($data = trim($data))) {
            return [];
        }

        $decodedValue = json_decode($data, true);
        $lastError    = json_last_error();
        $jsonErrors   = [
            JSON_ERROR_DEPTH            => 'Maximum stack depth exceeded',
            JSON_ERROR_CTRL_CHAR        => 'Control character error, possibly incorrectly encoded',
            JSON_ERROR_SYNTAX           => 'Syntax error',
            JSON_ERROR_UTF8             => 'Malformed UTF-8 characters, possibly incorrectly encoded',
            JSON_ERROR_RECURSION        => 'Recursion detected',
            JSON_ERROR_INF_OR_NAN       => 'Inf and NaN cannot be JSON encoded',
            JSON_ERROR_UNSUPPORTED_TYPE => 'Unsupported type',
        ];

        if ($lastError !== JSON_ERROR_NONE) {
            $errorMessage = $jsonErrors[$lastError] ?? 'Unknown error';
            throw new JsonDecodeException(
                sprintf('%s. Related data: %s', $errorMessage, json_encode($data))
            );
        }

        return $decodedValue;
    }

    /**
     * Encode json
     *
     * @param  mixed $value val
     */
    public static function jsonEncode($value): string
    {
        // We need to use JSON_UNESCAPED_SLASHES because JavaScript's native
        // JSON.stringify function uses this feature by default
        //
        // https://stackoverflow.com/questions/10314715/why-is-json-encode-adding-backslashes
        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Paginator json decode
     *
     * @param  object|array $items   data
     */
    public static function paginatorJsonDecode($items): array
    {
        if (empty($items)) {
            return [];
        }

        $jsonErrors = [
            JSON_ERROR_DEPTH     => 'Maximum heap size exceeded',
            JSON_ERROR_CTRL_CHAR => 'Control character error, possibly incorrectly encoded',
            JSON_ERROR_SYNTAX    => 'Syntax error',
        ];

        $newData = [];
        foreach ($items as $key => $row) {
            foreach ($row as $field => $value) {
                if (is_string($value) && (strpos($value, '[{"') === 0 || strpos($value, '{"') === 0)) {  // if json encoded value
                    $decodedValue          = json_decode($value, true);
                    $lastError             = json_last_error();
                    $newData[$key][$field] = $lastError === JSON_ERROR_NONE ? $decodedValue : $jsonErrors[$lastError] . ': ' . $value;
                } else {
                    $newData[$key][$field] = $value;
                }
            }
        }

        return $newData;
    }
}
