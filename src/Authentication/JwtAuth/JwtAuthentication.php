<?php

declare(strict_types=1);

namespace Olobase\Authentication\JwtAuth;

use Laminas\Authentication\Adapter\AdapterInterface;
use Laminas\Diactoros\Response\JsonResponse;
use Mezzio\Authentication\UserInterface;
use Olobase\Authentication\JwtAuth\JwtAuthenticationInterface;
use Olobase\Authentication\JwtAuth\JwtEncoderInterface;
use Olobase\Authentication\JwtAuth\TokenInterface;
use Olobase\Authorization\Contract\RoleModelInterface;
use Olobase\Util\StringHelper;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

use function array_key_exists;
use function in_array;
use function md5;
use function preg_match;

class JwtAuthentication implements JwtAuthenticationInterface
{
    public const USERNAME_FIELD                        = 'username';
    public const PASSWORD_FIELD                        = 'password';
    public const TOKEN_DECRYPTION_FAILED               = 'tokenDecryptionFailed';
    public const AUTHENTICATION_REQUIRED               = 'authenticationRequired';
    public const IP_VALIDATION_FAILED                  = 'ipValidationFailed';
    public const USER_AGENT_VALIDATION_FAILED          = 'userAgentValidationFailed';
    public const USERNAME_OR_PASSWORD_INCORRECT        = 'usernameOrPasswordIncorrect';
    public const ACCOUNT_IS_INACTIVE_OR_SUSPENDED      = 'accountIsInactiveOrSuspended';
    public const USERNAME_OR_PASSWORD_FIELDS_NOT_GIVEN = 'usernameOrPasswordNotGiven';
    public const NO_ROLE_DEFINED_ON_THE_ACCOUNT        = 'noRoleDefinedOnAccount';

    protected static $messageTemplates = [
        self::TOKEN_DECRYPTION_FAILED               => 'Token decryption failed',
        self::AUTHENTICATION_REQUIRED               => 'Authentication required. Please sign in to your account',
        self::USERNAME_OR_PASSWORD_INCORRECT        => 'Username or password is incorrect',
        self::ACCOUNT_IS_INACTIVE_OR_SUSPENDED      => 'This account is awaiting approval or suspended',
        self::USERNAME_OR_PASSWORD_FIELDS_NOT_GIVEN => 'Username and password fields must be given',
        self::NO_ROLE_DEFINED_ON_THE_ACCOUNT        => 'There is no role defined for this user',
        self::IP_VALIDATION_FAILED                  => 'Ip validation failed and you are logged out',
        self::USER_AGENT_VALIDATION_FAILED          => 'Browser validation failed and you are logged out',
    ];
    protected $rawToken;
    protected $request;
    protected $rowObject;
    protected $userFactory;
    protected $ipAddress;
    protected $payload = [];
    protected $error;
    protected $code;
    protected $identityColumn;
    protected $excludedFields;

    public function __construct(
        private array $config,
        private AdapterInterface $authAdapter,
        private JwtEncoderInterface $jwtEncoder,
        private TokenInterface $token,
        private RoleModelInterface $roleModel,
        callable $userFactory
    ) {
        $this->userFactory    = $userFactory;
        $this->excludedFields = $config['authentication']['sensitive_fields'] ?? ['password'];
        $this->identityColumn = $config['authentication']['adapter']['options']['identity_column'];
    }

    public function authenticate(ServerRequestInterface $request): ?UserInterface
    {
        $this->request = $request;
        $clientIp      = $request->getAttribute('client_ip', 'unknown');
        $this->setIpAddress($clientIp);

        if (! $this->isTokenValid()) {
            return null;
        }
        if (false == $this->isIpAddressValid() || false == $this->isUserAgentValid()) {
            return null;
        }
        $payload = $this->getTokenPayload()['data'];
        $data    = (array) $payload;
        return ($this->userFactory)($data['details']->{$this->identityColumn}, (array) $data['roles'], (array) $data['details']);
    }

    public function authenticateWithCredentials(ServerRequestInterface $request): ?UserInterface
    {
        $this->request = $request;
        $post          = $request->getParsedBody();
        $clientIp      = $request->getAttribute('client_ip', 'unknown');
        $this->setIpAddress($clientIp);

        $usernameField = $this->config['authentication']['form'][self::USERNAME_FIELD];
        $passwordField = $this->config['authentication']['form'][self::PASSWORD_FIELD];

        if (! array_key_exists($usernameField, $post) || ! array_key_exists($passwordField, $post)) {
            $this->setError(self::USERNAME_OR_PASSWORD_FIELDS_NOT_GIVEN);
            return null;
        }
        $this->authAdapter->setIdentity($post[$usernameField]);
        $this->authAdapter->setCredential($post[$passwordField]);

        $usernameValue = $post[$usernameField];
        $result        = $this->attemptAuthentication($usernameValue);
        if (! $result) {
            return null;
        }
        $this->rowObject = $this->authAdapter->getResultRowObject(); // create authenticated user object

        if ($this->isUserInactive()) {
            return null;
        }
        $roles = $this->getUserRoles();
        if (! $roles) {
            return null;
        }
        $userDetails = $this->buildUserDetailsData();
        return ($this->userFactory)($result->getIdentity(), (array) $roles, $userDetails);
    }

    public function getToken(): TokenInterface
    {
        return $this->token;
    }

    public function getError()
    {
        return $this->setError;
    }

    public function getCode()
    {
        return $this->code;
    }

    public function unauthorizedResponse(ServerRequestInterface $request): ResponseInterface
    {
        return new JsonResponse(
            [
                'data' => [
                    'code'  => $this->getCode(),
                    'error' => $this->getError(),
                ],
            ],
            401,
            ['WWW-Authenticate' => 'Bearer realm="Jwt token"']
        );
    }

    protected function isUserInactive(): bool
    {
        if (empty($this->rowObject->is_active)) {
            $this->setError(self::ACCOUNT_IS_INACTIVE_OR_SUSPENDED);
            return true;
        }
        return false;
    }

    protected function attemptAuthentication($usernameValue)
    {
        try {
            $result = $this->authAdapter->authenticate();
        } catch (Throwable $e) {
            throw $e->getPrevious() ?: $e;
        }
        if (! $result->isValid()) {
            $this->setError(self::USERNAME_OR_PASSWORD_INCORRECT);
            return false;
        }
        return $result;
    }

    protected function getUserRoles(): array|bool
    {
        $roles = $this->roleModel->findRolesByUserId($this->rowObject->id);
        if (empty($roles)) {
            $this->setError(self::NO_ROLE_DEFINED_ON_THE_ACCOUNT);
            return false;
        }
        return $roles;
    }

    protected function buildUserDetailsData(): array
    {
        $rowArray       = (array) $this->rowObject;
        $excludedFields = $this->getExcludedFields();
        $formattedArray = [];
        foreach ($rowArray as $key => $value) {
            if (in_array($key, $excludedFields, true)) {
                continue; // skip sensitive columns
            }
            $camelKey                  = StringHelper::snakeToCamel($key);
            $formattedArray[$camelKey] = $value;
        }
        $formattedArray['ipAddress'] = $this->getIpAddress();
        $formattedArray['deviceKey'] = $this->generateDeviceKey($this->request);

        return $formattedArray;
    }

    protected function isTokenValid(): bool
    {
        $this->rawToken = $this->parseBearerToken(); // parse token from headers
        if (! $this->token) {
            $this->setError(self::AUTHENTICATION_REQUIRED);
            return false;
        }
        $rawToken = $this->decryptTokenOrNull($this->rawToken);  // decrypt token
        if (! $rawToken) {
            $this->setError(self::TOKEN_DECRYPTION_FAILED);
            return false;
        }
        $this->payload = $this->jwtEncoder->decode($rawToken);
        return $this->payload !== null;
    }

    protected function decryptTokenOrNull(string $rawToken)
    {
        try {
            return $this->token->getTokenEncryptHelper()->decrypt($rawToken);
        } catch (Exception $e) {
            return null;
        }
    }

    protected function isIpAddressValid(): bool
    {
        if (
            $this->config['authentication']['token']['validation']['user_ip']
            && $this->payload['data']->details->ipAddress != $this->getIpAddress()
        ) {
            $this->token->revoke(
                $this->payload['data']->id,
                $this->payload['jti'],
            );
            $this->setError(self::IP_VALIDATION_FAILED);
            return false;
        }
        return true;
    }

    protected function isUserAgentValid(): bool
    {
        if (
            $this->config['authentication']['token']['validation']['user_agent']
            && $this->payload['data']->details->deviceKey != $this->generateDeviceKey($this->request)
        ) {
            $this->token->revoke(
                $this->payload['data']->id,
                $this->payload['jti'],
            );
            $this->setError(self::USER_AGENT_VALIDATION_FAILED);
            return false;
        }
        return true;
    }

    protected function getRawToken(): string
    {
        return $this->rawToken;
    }

    protected function getTokenPayload(): array
    {
        return $this->payload;
    }

    protected function parseBearerToken(): ?string
    {
        $authHeader = $this->request->getHeader('Authorization');
        if (empty($authHeader)) {
            return null;
        }
        if (preg_match("/Bearer\s+(.*)$/i", $authHeader[0], $matches)) {
            return $matches[1] == "null" ? null : $matches[1];
        }
        return null;
    }

    public function setError(string $errorKey)
    {
        $this->setError = $errorKey;
    }

    public function setCode(string $code)
    {
        $this->code = $code;
    }

    public function getMessageTemplates(): array
    {
        return self::$messageTemplates;
    }

    protected function setIpAddress(string $ipAddress)
    {
        $this->ipAddress = $ipAddress;
        $this->token->setIpAddress($ipAddress);
    }

    protected function getIpAddress(): string
    {
        return $this->ipAddress;
    }

    protected function getExcludedFields(): array
    {
        return $this->excludedFields;
    }

    protected function generateDeviceKey()
    {
        $server    = $this->request->getServerParams();
        $userAgent = empty($server['HTTP_USER_AGENT']) ? 'unknown' : $server['HTTP_USER_AGENT'];
        return md5($userAgent);
    }
}
