<?php

declare(strict_types=1);

namespace Olobase\Authentication\JwtAuth;

use Psr\Http\Message\ServerRequestInterface;
use Olobase\Authentication\Util\TokenEncryptHelper;

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
     *
     * @return TokenEncryptHelper
     */
    public function getTokenEncryptHelper(): TokenEncryptHelper;
}
