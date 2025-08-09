<?php

declare(strict_types=1);

namespace Olobase\Util;

use function array_slice;
use function count;
use function explode;
use function filter_var;
use function implode;
use function in_array;
use function preg_replace;
use function strlen;
use function strtolower;
use function trim;

use const FILTER_FLAG_IPV4;
use const FILTER_FLAG_IPV6;
use const FILTER_VALIDATE_IP;

class RequestHelper
{
    /**
     * Get origin
     *
     * https://stackoverflow.com/questions/276516/parsing-domain-from-a-url
     *
     * @param  string $host $server['SERVER_NAME']
     */
    public static function getOrigin(string $host): ?string
    {
        if (! $host) {
            return null;
        }
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return $host;
        }
        $host  = strtolower(trim($host));
        $host  = preg_replace('/^www\./', '', $host);
        $parts = explode('.', $host);
        $count = count($parts);

        if ($count >= 3 && strlen($parts[$count - 1]) === 2 && strlen($parts[$count - 2]) <= 3) {
            return implode('.', array_slice($parts, -3)); // example.co.uk
        }

        if ($count >= 2) {
            return implode('.', array_slice($parts, -2));
        }
        return null;
    }

    /**
     * Returns to user real ip address, respecting trusted proxies.
     *
     * @param  array       $server          php global $_SERVER parameters
     * @param  array       $trustedProxies  Array of trusted proxy IP addresses
     * @param  string      $default         Default value if no valid IP found
     * @param  int         $options         filter_var options
     */
    public static function getRealUserIp(
        array $server = [],
        array $trustedProxies = [],
        ?string $default = 'unknown',
        int $options = FILTER_FLAG_IPV4 | FILTER_FLAG_IPV6
    ): ?string {
        if (empty($server)) {
            $server = $_SERVER;
        }

        $remoteAddr = $server['REMOTE_ADDR'] ?? null;

        if ($remoteAddr === null) {
            return $default;
        }

        if (! in_array($remoteAddr, $trustedProxies, true)) {
            if (filter_var($remoteAddr, FILTER_VALIDATE_IP, $options)) {
                return $remoteAddr;
            }
            return $default;
        }

        $ipHeaders = [
            'HTTP_CF_CONNECTING_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_CLIENT_IP',
        ];
        foreach ($ipHeaders as $header) {
            if (! empty($server[$header])) {
                $ips = explode(',', $server[$header]);
                foreach ($ips as $ip) {
                    $ip = trim($ip);
                    if (filter_var($ip, FILTER_VALIDATE_IP, $options)) {
                        return $ip;
                    }
                }
            }
        }
        return $default;
    }
}
