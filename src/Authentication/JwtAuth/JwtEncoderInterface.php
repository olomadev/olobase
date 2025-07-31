<?php

declare(strict_types=1);

namespace Olobase\Authentication\Service;

interface JwtEncoderInterface
{
    /**
     * Encode array data to jwt token string
     *
     * @param  array  $payload    array
     * @return string
     */
    public function encode(array $payload): string;

    /**
     * Decode token as array
     *
     * @param  string $token     token
     * @return array
     */
    public function decode(string $token): array;
}
