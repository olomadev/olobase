<?php

declare(strict_types=1);

namespace Olobase\Authentication\JwtAuth;

use Olobase\Util\RequestHelper;
use Olobase\Authentication\Util\TokenEncryptHelper;
use Olobase\Exception\ConfigurationErrorException;
use Psr\Http\Message\ServerRequestInterface;
use Mezzio\Authentication\UserInterface;

abstract class AbstractToken implements TokenInterface
{
    protected int $leeway = 10;
    protected int $tokenValidity = 50;

    public function __construct(
        array $config,
        protected TokenEncryptHelper $tokenEncrypt,
        protected JwtEncoderInterface $jwtEncoder,
    ) {
        $this->leeway = intval($config['token']['leeway']);
        $this->tokenValidity = intval($config['token']['token_validity'] * 60);
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
     * @return array
     */
    protected function generateHeader(ServerRequestInterface $request, ?int $expiration = null): array
    {
        $server = $request->getServerParams();
        $tokenId = bin2hex(random_bytes(16));
        $issuedAt = time();
        $notBefore = $issuedAt;
        $ttl = $expiration ?? ($this->tokenValidity);
        $expire = $notBefore + $ttl;
        $http = empty($server['HTTPS']) ? 'http://' : 'https://';
        $issuer = $http . $server['HTTP_HOST'];
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
            throw new \RuntimeException("User attribute is missing in request");
        }
        //
        // JWT header
        //
        list(
            $tokenId,
            $issuedAt,
            $notBefore,
            $expire,
            $issuer,
            $deviceKey
        ) = $this->generateHeader($request, $expiration);

        $date = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->setTimestamp($expire);
        $details = $user->getDetails();
        $userId = $details['id'];
        $data =  [                  // Data related to the signer user
            'roles' => $user->getRoles(),
            'details' => $details,
            'meta' => [
                'tokenId' => $tokenId,
                'ipAddress' => RequestHelper::getRealUserIp($request->getServerParams()),
                'deviceKey' => $deviceKey,
                'expiresAt' => $date->format('Y-m-d\TH:i:s.v\Z'),
            ]
        ];
        //
        // JWT token data
        //
        $jwt = [
            'iat'  => $issuedAt,         // Issued at: time when the token was generated
            'jti'  => $tokenId,          // Json Token Id: an unique identifier for the token
            'iss'  => $issuer,           // Issuer
            'nbf'  => $notBefore,        // Not before
            'exp'  => $expire,           // Expire
            'data' => $data,
        ];
        $token = $this->jwtEncoder->encode($jwt);

        return [
            'data' => $data,
            'token' => $this->tokenEncrypt->encrypt($token),
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
        $server = $request->getServerParams();
        $userId = $decoded['data']['details']['id'];
        $currentExpire = $decoded['exp'];
        //
        // Check token expire
        //
        if ($currentExpire < (time() - $this->leeway)) {
            return false; // ttl expired
        }
        //
        // JWT header - renew token
        //
        list(
            $tokenId,
            $issuedAt,
            $notBefore,
            $expire,
            $issuer,
            $deviceKey
        ) = $this->generateHeader($request, $expiration);
        //
        // Renew JWT token data
        //
        $date = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->setTimestamp($expire);
        $decoded['data']['meta']['tokenId'] = $tokenId;
        $decoded['data']['meta']['ipAddress'] = RequestHelper::getRealUserIp($request->getServerParams());
        $decoded['data']['meta']['deviceKey'] = $deviceKey;
        $decoded['data']['meta']['expiresAt'] = $date->format('Y-m-d\TH:i:s.v\Z');
        $jwt = [
            'iat'  => $issuedAt,        // Issued at: time when the token was generated
            'jti'  => $tokenId,         // Json Token Id: an unique identifier for the token
            'iss'  => $decoded['iss'],  // Issuer
            'nbf'  => $notBefore,       // Not before
            'exp'  => $expire,          // Expire
            'data' => (array)$decoded['data']
        ];
        $newToken = $this->jwtEncoder->encode($jwt);

        return [
            'data' => (array)$decoded['data'],
            'token' => $this->tokenEncrypt->encrypt($newToken),
        ];
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
