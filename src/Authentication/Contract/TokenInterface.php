<?php

declare(strict_types=1);

namespace Olobase\Authentication\Contract;

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
    public function decode(string $token);
    
    /**
     * Returns to encoded token with expire date
     *
     * @param  ServerRequestInterface $request request
     * @param  integer                 $expiration  user can set expiration value optionally
     * @return array|boolean
     */
    public function create(ServerRequestInterface $request, $expiration = null);

    /**
     * Refresh token
     * 
     * @param  ServerRequestInterface $request request
     * @param  array                  $decoded payload data
     * @param  integer                $expiration user can set expiration value optionally
     * @return array|boolean
     */
    public function refresh(ServerRequestInterface $request, array $decoded, $expiration = null);

    /**
     * Kill current token for logout operation
     * 
     * @param  string $userId  user id
     * @param  string $tokenId token id
     * @return void
     */
    public function kill(string $userId, string $tokenId);

    /**
     * Returns the token encryption helper object
     * 
     * @return TokenEncryptHelper
     */
    public function getTokenEncrypt(): TokenEncryptHelper;
}
