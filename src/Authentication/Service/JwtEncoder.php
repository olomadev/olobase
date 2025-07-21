<?php

declare(strict_types=1);

namespace Olobase\Authentication\Service;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Olobase\Exception\JwtEncoderException;

/**
 * @author Oloma <support@oloma.dev>
 *
 * https://github.com/firebase/php-jwt
 * 
 * Column filters
 */
final class JwtEncoder implements JwtEncoderInterface
{
    private $publicKey;
    private $privateKey;

    public function __construct(array $config)
    {
        $token = $config['token'];
        if (empty($token['public_key']) || empty($token['private_key'])) {
            throw new JwtEncoderException(
                "Public or private keys cannot not be empty in your token configuration"
            );
        }
        $this->publicKey = $token['public_key'];
        $this->privateKey = $token['private_key'];
    }

    /**
     * Encode array data to jwt token string
     * 
     * @param  array  $payload    array
     * @return string
     */
    public function encode(array $payload): string
    {
        return JWT::encode($payload, $this->privateKey, 'EdDSA');
    }

    /**
     * Decode token as array
     * 
     * @param  string $token     token
     * @return array
     */
    public function decode(string $token): array
    {
        JWT::$leeway = 60;
        $decoded = JWT::decode($token, new Key($this->publicKey, 'EdDSA'));
        return (array)$decoded;
    }
}
