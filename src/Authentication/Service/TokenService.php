<?php

declare(strict_types=1);

namespace Olobase\Authentication\Service;

use Olobase\Authentication\Helper\TokenEncryptHelper;
use Olobase\Authentication\Contracts\JwtEncoderInterface;
use Olobase\Authentication\Contracts\TokenInterface;
use Olobase\Exception\ConfigurationErrorException;
use Laminas\Cache\Storage\StorageInterface;
use Psr\Http\Message\ServerRequestInterface;
use Mezzio\Authentication\UserInterface;
use Common\Helper\RequestHelper;

class TokenService implements TokenInterface
{
    protected $config;

    public const SESSION_KEY = 'session:';

    public function __construct(
        array $config,
        protected StorageInterface $cache,
        protected TokenEncryptHelper $tokenEncrypt,
        protected JwtEncoderInterface $encoder
    )
    {
        $this->config = $config;
        $sessionTTL = $this->config['token']['session_ttl'] * 60;
        if ($sessionTTL < 10) {
            throw new ConfigurationErrorException(
                "Session ttl value cannot be less than 10 minutes"
            );
        }
    }
    
    /**
     * Returns to session key
     * 
     * @return string
     */
    public function getSessionKey(): string
    {
        return APP_CACHE_PREFIX . self::SESSION_KEY;
    }

    /**
     * Decode token
     * 
     * @param  string $token token
     * @return mixed
     */
    public function decode(string $token)
    {
        return $this->encoder->decode($token);
    }
    
    /**
     * Generate token header variables
     * 
     * @param  ServerRequestInterface $request object
     * @param  integer                 $expiration  user can set expiration value optionally
     * @return array
     */
    protected function generateHeader(ServerRequestInterface $request, ?int $expiration = null) : array
    {
        $server = $request->getServerParams();
        $tokenId = bin2hex(random_bytes(16));
        $issuedAt = time();
        $notBefore = $issuedAt;
        $ttl = $expiration ?? ($this->config['token']['token_validity'] * 60);
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
    public function create(ServerRequestInterface $request, $expiration = null)
    {
        $user = $request->getAttribute(UserInterface::class);
        //
        // JWT header
        //
        list(
            $tokenId,
            $issuedAt,
            $notBefore,
            $expire,
            $issuer
        ) = $this->generateHeader($request, $expiration);

        $userDetails = $user->getDetails();
        unset($userDetails['avatar']);
        //
        // Generate user data
        //
        $details = array_merge($userDetails, [
            'tokenId' => $tokenId,
            'email' => $user->getDetail('email') ?? $user->getIdentity(),
            'ip' => RequestHelper::getRealUserIp(),
            'deviceKey' => md5($request->getServerParams()['HTTP_USER_AGENT'] ?? 'unknown'),
        ]);
        $userId = $details['id'];
        //
        // JWT token data
        //
        $jwt = [
            'iat'  => $issuedAt,         // Issued at: time when the token was generated
            'jti'  => $tokenId,          // Json Token Id: an unique identifier for the token
            'iss'  => $issuer,           // Issuer
            'nbf'  => $notBefore,        // Not before
            'exp'  => $expire,           // Expire
            'data' => [                  // Data related to the signer user
                'roles' => $user->getRoles(),
                'details' => $details,
            ]
        ];
        $token = $this->encoder->encode($jwt);
        //
        // create token session
        //
        $configSessionTTL = (int)$this->config['token']['session_ttl'] * 60;
        $this->cache->getOptions()->setTtl($configSessionTTL);
        $this->cache->setItem($this->getSessionKey().$userId.":".$tokenId, $configSessionTTL);

        return [
            'token' => $this->tokenEncrypt->encrypt($token),
            'tokenId' => $tokenId,
            'expiresAt' => date('Y-m-d H:i:s', $expire),
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
    public function refresh(ServerRequestInterface $request, array $decoded, $expiration = null)
    {
        $server = $request->getServerParams();
        $userAgent = empty($server['HTTP_USER_AGENT']) ? 'unknown' : $server['HTTP_USER_AGENT'];
        $userId = $decoded['data']['details']['id'];
        //
        // validate token session
        //
        $oldTokenId = $decoded['data']['details']['tokenId'];
        $sessionTTL = $this->cache->getItem($this->getSessionKey().$userId.":".$oldTokenId);
        if (! $sessionTTL) {
            return false; // ttl expired
        }
        $currentExpire = $decoded['exp'];
        if ($currentExpire + 10 < time()) {
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
        $decoded['data']['details']['tokenId'] = $tokenId; // renew token id
        $decoded['data']['details']['ip'] = RequestHelper::getRealUserIp(); 
        $decoded['data']['details']['deviceKey'] = $deviceKey; 
        $jwt = [
            'iat'  => $decoded['iat'],  // Issued at: time when the token was generated
            'jti'  => $tokenId,         // Json Token Id: an unique identifier for the token
            'iss'  => $decoded['iss'],  // Issuer
            'nbf'  => $notBefore,       // Not before
            'exp'  => $expire,          // Expire
            'data' => (array)$decoded['data']
        ];
        $newToken = $this->encoder->encode($jwt);
        //
        // refresh the user session
        //
        $configSessionTTL = (int)$this->config['token']['session_ttl'] * 60;
        $this->cache->getOptions()->setTtl($configSessionTTL);
        $this->cache->setItem($this->getSessionKey().$userId.":".$tokenId, $configSessionTTL);
        $this->cache->removeItem($this->getSessionKey().$userId.":".$oldTokenId);

        return [
            'token' => $this->tokenEncrypt->encrypt($newToken),
            'tokenId' => $tokenId,
            'expiresAt' => date("Y-m-d H:i:s", $expire),
            'data' => (array)$decoded['data']
        ];
    }

    /**
     * Kill current token for logout operation
     * 
     * @param  string $userId  user id
     * @param  string $tokenId token id
     * @return void
     */
    public function kill(string $userId, string $tokenId)
    {
        $this->cache->removeItem($this->getSessionKey().$userId.":".$tokenId);
    }
    
    /**
     * Returns to token encryption object
     * 
     * @return object
     */
    public function getTokenEncrypt() : TokenEncryptHelper
    {
        return $this->tokenEncrypt;
    }

}