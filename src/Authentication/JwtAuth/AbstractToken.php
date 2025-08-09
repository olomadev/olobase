<?php

declare(strict_types=1);

namespace Olobase\Authentication\JwtAuth;

use DateTimeImmutable;
use DateTimeZone;
use Mezzio\Authentication\UserInterface;
use Olobase\Authentication\Util\TokenEncryptHelper;
use Olobase\Util\RequestHelper;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;

use function array_key_exists;
use function bin2hex;
use function intval;
use function md5;
use function random_bytes;
use function time;

abstract class AbstractToken implements TokenInterface
{
    protected int $leeway          = 10;
    protected array $jwtUserFields = [];
    protected int $tokenValidity   = 50;
    protected string $ipAddress    = 'unknown';

    public function __construct(
        array $config,
        protected TokenEncryptHelper $tokenEncrypt,
        protected JwtEncoderInterface $jwtEncoder,
    ) {
        $this->leeway        = intval($config['authentication']['token']['leeway']);
        $this->jwtUserFields = $config['authentication']['jwt_user_fields'];
        $this->tokenValidity = intval($config['authentication']['token']['token_validity'] * 60);
    }

    /**
     * Decode token
     *
     * @param  string $token token
     * @return mixed
     */
    public function decodeToken(string $token)
    {
        return $this->jwtEncoder->decode($token);
    }

    /**
     * Generate token header variables
     *
     * @param  ServerRequestInterface $request object
     * @param  integer                 $expiration  user can set expiration value optionally
     */
    protected function generateHeader(ServerRequestInterface $request, ?int $expiration = null): array
    {
        $server    = $request->getServerParams();
        $tokenId   = bin2hex(random_bytes(16));
        $issuedAt  = time();
        $notBefore = $issuedAt;
        $ttl       = $expiration ?? ($this->tokenValidity);
        $expire    = $notBefore + $ttl;
        $http      = empty($server['HTTPS']) ? 'http://' : 'https://';
        $issuer    = $http . $server['HTTP_HOST'];
        $userAgent = $server['HTTP_USER_AGENT'] ?? 'unknown';
        $deviceKey = md5($userAgent);

        return [$tokenId, $issuedAt, $notBefore, $expire, $issuer, $deviceKey];
    }

    /**
     * Returns to encoded token with expire date
     *
     * @param  ServerRequestInterface $request request
     * @param  integer                 $expiration  user can set expiration value optionally
     * @return array|boolean
     */
    public function generateToken(ServerRequestInterface $request, $expiration = null)
    {
        $user = $request->getAttribute(UserInterface::class);
        if (! $user instanceof UserInterface) {
            throw new RuntimeException("User attribute is missing in request");
        }
        // JWT header
        [
            $tokenId,
            $issuedAt,
            $notBefore,
            $expire,
            $issuer,
            $deviceKey,
        ] = $this->generateHeader($request, $expiration);

        $date           = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->setTimestamp($expire);
        $details        = $user->getDetails();
        $jwtUserDetails = [];
        foreach ($this->jwtUserFields as $column) {
            if (array_key_exists($column, $details)) {
                $jwtUserDetails[$column] = $details[$column];
            }
        }
        $data = [                  // Data related to the signer user
            'roles'   => $user->getRoles(),
            'details' => $jwtUserDetails,
            'meta'    => [
                'tokenId'   => $tokenId,
                'ipAddress' => $this->getIpAddress(),
                'deviceKey' => $deviceKey,
                'expiresAt' => $date->format('Y-m-d\TH:i:s.v\Z'),
            ],
        ];
        // JWT token data
        $jwt   = [
            'iat'  => $issuedAt, // Issued at: time when the token was generated
            'nbf'  => $notBefore, // Not before
            'exp'  => $expire, // Expire
            'data' => $data,
        ];
        $token = $this->jwtEncoder->encode($jwt);

        return [
            'data'  => $data,
            'token' => $this->tokenEncrypt->encrypt($token),
            'extra' => $details,
        ];
    }

    /**
     * Refresh token
     *
     * @param  ServerRequestInterface $request request
     * @param  array                  $decoded payload data
     * @param  integer                $expiration user can set expiration value optionally
     * @return array|boolean
     */
    public function refreshToken(ServerRequestInterface $request, array $decoded, $expiration = null)
    {
        $server        = $request->getServerParams();
        $userId        = $decoded['data']['details']['id'];
        $currentExpire = $decoded['exp'];
        // Check token expire
        if ($currentExpire < time() - $this->leeway) {
            return false; // ttl expired
        }
        // JWT header - renew token
        [
            $tokenId,
            $issuedAt,
            $notBefore,
            $expire,
            $issuer,
            $deviceKey,
        ] = $this->generateHeader($request, $expiration);
        // Renew JWT token data
        $date                                 = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->setTimestamp($expire);
        $decoded['data']['meta']['tokenId']   = $tokenId;
        $decoded['data']['meta']['ipAddress'] = RequestHelper::getRealUserIp($request->getServerParams());
        $decoded['data']['meta']['deviceKey'] = $deviceKey;
        $decoded['data']['meta']['expiresAt'] = $date->format('Y-m-d\TH:i:s.v\Z');
        $jwt                                  = [
            'iat'  => $issuedAt, // Issued at: time when the token was generated
            'nbf'  => $notBefore, // Not before
            'exp'  => $expire, // Expire
            'data' => (array) $decoded['data'],
        ];
        $newToken                             = $this->jwtEncoder->encode($jwt);

        return [
            'data'  => (array) $decoded['data'],
            'token' => $this->tokenEncrypt->encrypt($newToken),
        ];
    }

    /**
     * Set user ip adress
     *
     * @param string $ipAddress ip address
     */
    public function setIpAddress(string $ipAddress)
    {
        $this->ipAddress = $ipAddress;
    }

    /**
     * Returns to user ip address
     */
    public function getIpAddress(): string
    {
        return $this->ipAddress;
    }

    /**
     * Returns to token encryption object
     *
     * @return object
     */
    public function getTokenEncryptHelper(): TokenEncryptHelper
    {
        return $this->tokenEncrypt;
    }
}
