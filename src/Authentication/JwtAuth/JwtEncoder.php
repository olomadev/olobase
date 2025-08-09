<?php

declare(strict_types=1);

namespace Olobase\Authentication\JwtAuth;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Olobase\Exception\JwtEncoderException;

final class JwtEncoder implements JwtEncoderInterface
{
    private int $leeway;
    private string $algorithm;
    private string $publicKey;
    private string $privateKey;

    public function __construct(array $config)
    {
        $token = $config['authentication']['token'] ?? [];
        if (empty($token['public_key']) || empty($token['private_key'])) {
            throw new JwtEncoderException("Public or private keys cannot be empty in your token configuration.");
        }
        if (empty($token['algorithm'])) {
            throw new JwtEncoderException("Token algorithm cannot be empty.");
        }
        $this->publicKey  = $token['public_key'];
        $this->privateKey = $token['private_key'];
        $this->algorithm  = $token['algorithm'];
        $this->leeway     = isset($token['leeway']) ? (int) $token['leeway'] : 60;
    }

    /**
     * Encode array data to JWT token string
     */
    public function encode(array $payload): string
    {
        return JWT::encode($payload, $this->privateKey, $this->algorithm);
    }

    /**
     * Decode token as array
     */
    public function decode(string $token): array
    {
        JWT::$leeway = $this->leeway;
        $decoded     = JWT::decode($token, new Key($this->publicKey, $this->algorithm));
        return (array) $decoded;
    }
}
