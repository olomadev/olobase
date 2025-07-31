<?php

declare(strict_types=1);

namespace Olobase\Authentication\Service;

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
    public function authenticateWithCredentials(ServerRequestInterface $request): ?UserInterface;

    /**
     * Set authentication error
     *
     * @param string $errorKey predefiend in error message templates
     */
    public function setError(string $errorKey);

    /**
     * Set authentication error code
     *
     * @param string $code predefined in constants
     */
    public function setCode(string $code);

    /**
     * Returns to defined error message templates for error codes.
     *
     * @return array
     */
    public function getMessageTemplates() : array;

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

    /**
     * Returns to token service class
     *
     * @return TokenServiceInterface
     */
    public function getToken(): TokenInterface;
}
