<?php

declare(strict_types=1);

namespace Olobase\Authentication\Contract;

use Psr\Http\Message\ServerRequestInterface;
use Mezzio\Authentication\AuthenticationInterface;
use Olobase\Authentication\Service\TokenServiceInterface;

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
    public function getTokenService(): TokenServiceInterface;

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