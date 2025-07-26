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
    public static function getOrigin($host) {
        if (!$host) {
            return null;
        }
        
        // Check if the host is an IP address
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return $host; // Return the IP as the origin
        }
        
        // Remove 'www.' and split the domain
        $domainArray = explode('.', str_replace('www.', '', $host));
        $count = count($domainArray);
        
        // Handle second-level domains (example.co.uk)
        if ($count >= 3 && strlen($domainArray[$count - 2]) == 2) {
            return implode('.', array_splice($domainArray, $count - 3, 3));
        }
        
        // Handle top-level domains (example.com)
        if ($count >= 2) {
            return implode('.', array_splice($domainArray, $count - 2, 2));
        }

        return null; // In case the domain does not meet criteria
    }

    /**
     * Get user real ip if proxy used
     * 
     * @param  string|null  $default Default value to return if no valid IP is found
     * @param  int $options Options for the filter_var function (optional)
     * @return string|null
     */
    public static function getRealUserIp($default = null, $options = FILTER_FLAG_IPV4 | FILTER_FLAG_IPV6) 
    {
        // Cloudflare and other proxy support
        $HTTP_CF_CONNECTING_IP = isset($_SERVER["HTTP_CF_CONNECTING_IP"]) ? $_SERVER["HTTP_CF_CONNECTING_IP"] : getenv('HTTP_CF_CONNECTING_IP');
        $HTTP_X_FORWARDED_FOR = isset($_SERVER["HTTP_X_FORWARDED_FOR"]) ? $_SERVER["HTTP_X_FORWARDED_FOR"] : getenv('HTTP_X_FORWARDED_FOR');
        $HTTP_CLIENT_IP = isset($_SERVER["HTTP_CLIENT_IP"]) ? $_SERVER["HTTP_CLIENT_IP"] : getenv('HTTP_CLIENT_IP');
        $REMOTE_ADDR = isset($_SERVER["REMOTE_ADDR"]) ? $_SERVER["REMOTE_ADDR"] : getenv('REMOTE_ADDR');

        $allIps = explode(",", "$HTTP_X_FORWARDED_FOR,$HTTP_CLIENT_IP,$HTTP_CF_CONNECTING_IP,$REMOTE_ADDR");
        
        foreach ($allIps as $ip) {
            $ip = trim($ip); // Clean up any whitespace
            if (filter_var($ip, FILTER_VALIDATE_IP, $options)) {
                return $ip; // Return the first valid IP found
            }
        }

        // If no valid IP found, return the default or REMOTE_ADDR
        return $default ?: $REMOTE_ADDR;
    }
}
