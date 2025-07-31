<?php

declare(strict_types=1);

namespace Olobase\Util;

class RequestHelper
{
    /**
     * Get origin
     *
     * https://stackoverflow.com/questions/276516/parsing-domain-from-a-url
     *
     * @param  string $host $server['SERVER_NAME']
     * @return string|null
     */
    public static function getOrigin(string $host): ?string
    {
        if (!$host) {
            return null;
        }
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return $host;
        }
        $host = strtolower(trim($host));
        $host = preg_replace('/^www\./', '', $host);
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
     * Returns to user real ip address
     *
     * @param  array  $server  php global $_SERVER parameters
     * @param  string|null $default $default Default value to return if no valid IP is found
     * @param  int $options $options Options for the filter_var function (optional)
     * @return string|null
     */
    public static function getRealUserIp(array $server = [], ?string $default = null, int $options = FILTER_FLAG_IPV4 | FILTER_FLAG_IPV6): ?string
    {
        if (empty($server)) {
            $server = $_SERVER;
        }
        $ipHeaders = [
            'HTTP_CF_CONNECTING_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_CLIENT_IP',
            'REMOTE_ADDR',
        ];
        foreach ($ipHeaders as $header) {
            if (isset($server[$header])) {
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
