<?php

declare(strict_types=1);

namespace Olobase\Authentication\Util;

use function bin2hex;
use function openssl_decrypt;
use function openssl_encrypt;
use function pack;

use const OPENSSL_RAW_DATA;

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
        $token = $config['authentication']['token'];

        $this->iv        = $token['encryption']['iv'];
        $this->enabled   = $token['encryption']['enabled'];
        $this->secretKey = $token['encryption']['secret_key'];
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
        $encrypted = openssl_encrypt($data, self::CIPHER, $this->secretKey, OPENSSL_RAW_DATA, $this->iv);
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
        return openssl_decrypt(pack('H*', $data), self::CIPHER, $this->secretKey, OPENSSL_RAW_DATA, $this->iv);
    }
}
