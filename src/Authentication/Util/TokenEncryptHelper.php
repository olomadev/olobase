<?php

declare(strict_types=1);

namespace Olobase\Authentication\Util;

class TokenEncryptHelper
{
    private const CIPHER = "AES-256-CTR";
    
    protected $iv;
    protected $enabled = false;
    protected $secretKey;

    /**
     * Constructor
     * 
     * @param array $config framework config
     */
    public function __construct(array $config)
    {
        $this->iv = $config['token']['encryption']['iv'];
        $this->enabled = $config['token']['encryption']['enabled'];
        $this->secretKey = $config['token']['encryption']['secret_key'];
    }

    /**
     * Encrypt data
     * 
     * @param  string $data data
     * @return string
     */
    public function encrypt(string $data)
    {
        if (! $this->enabled) {
            return $data;
        }
        $encrypted = openssl_encrypt($data, Self::CIPHER, $this->secretKey, OPENSSL_RAW_DATA, $this->iv);
        return bin2hex($encrypted);
    }

    /**
     * Decrypt data
     * 
     * @param  string $data data
     * @return string
     */
    public function decrypt(string $data)
    {
        if (! $this->enabled) {
            return $data;
        }
        return openssl_decrypt(pack('H*', $data), Self::CIPHER, $this->secretKey, OPENSSL_RAW_DATA, $this->iv);
    }

}
