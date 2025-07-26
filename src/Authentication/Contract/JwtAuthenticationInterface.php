<?php

declare(strict_types=1);

namespace Olobase\Authentication\Contract;

use Psr\Http\Message\ServerRequestInterface;
use Mezzio\Authentication\UserInterface;
use Mezzio\Authentication\AuthenticationInterface;

interface JwtAuthenticationInterface extends AuthenticationInterface
{
	/**
	 * Executes database query for authentication and if the operation is successful, returns to User class.
	 * 
	 * @param  ServerRequestInterface $request request
	 * @return UserInterface
	 */
    public function authenticateWithCredentials(ServerRequestInterface $request) : ?UserInterface;

    /**
     * Returns to token service class
     * 
     * @return TokenServiceInterface
     */
    public function getTokenService(): TokenInterface;

    /**
     * Returns to  latest error key
     * 
     * @return string|null
     */
    public function getError();

    /**
     * Returns to latest error code
     * ,
     * @return string|null
     */
    public function getCode();
}