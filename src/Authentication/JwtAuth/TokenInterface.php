<?php

declare(strict_types=1);

namespace Olobase\Authentication\JwtAuth;

use Olobase\Authentication\Util\TokenEncryptHelper;
use Psr\Http\Message\ServerRequestInterface;

interface TokenInterface
{
    /**
     * Decode token
     *
     * @param  string $token token
     * @return mixed
     */
    public function decodeToken(string $token);

    /**
     * Returns to encoded token with expire date
     *
     * @param  ServerRequestInterface $request request
     * @param  integer                 $expiration  user can set expiration value optionally
     * @return array|boolean
     */
    public function generateToken(ServerRequestInterface $request, ?int $expiration = null);

    /**
     * Refresh token
     *
     * @param  ServerRequestInterface $request request
     * @param  array                  $decoded payload data
     * @param  integer                $expiration user can set expiration value optionally
     * @return array|boolean
     */
    public function refreshToken(ServerRequestInterface $request, array $decoded, ?int $expiration = null);

    /**
     * Returns the token encryption helper object
     */
    public function getTokenEncryptHelper(): TokenEncryptHelper;

    /**
     * Set user ip adress
     *
     * @param string $ipAddress ip address
     */
    public function setIpAddress(string $ipAddress);

    /**
     * Returns to user ip address
     */
    public function getIpAddress(): string;
}
