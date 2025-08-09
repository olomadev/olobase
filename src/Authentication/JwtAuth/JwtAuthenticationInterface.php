<?php

declare(strict_types=1);

namespace Olobase\Authentication\JwtAuth;

use Mezzio\Authentication\AuthenticationInterface;
use Mezzio\Authentication\UserInterface;
use Psr\Http\Message\ServerRequestInterface;

interface JwtAuthenticationInterface extends AuthenticationInterface
{
    /**
     * Executes database query for authentication and if the operation is successful, returns to User class.
     *
     * @param  ServerRequestInterface $request request
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
     */
    public function getMessageTemplates(): array;

    /**
     * Returns to  latest error key
     *
     * @return string|null
     */
    public function getError();

    /**
     * Returns to latest error code
     * ,
     *
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
