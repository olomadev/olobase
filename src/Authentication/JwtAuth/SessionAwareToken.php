<?php

declare(strict_types=1);

namespace Olobase\Authentication\JwtAuth;

use Olobase\Exception\ConfigurationErrorException;
use Olobase\Authentication\Util\TokenEncryptHelper;
use Psr\Http\Message\ServerRequestInterface;
use Mezzio\Authentication\UserInterface;
use Laminas\Cache\Storage\StorageInterface;

class SessionAwareToken extends StatelessToken
{
    protected $sessionTtl;

    public function __construct(
        array $config,
        protected StorageInterface $cache,
        protected TokenEncryptHelper $tokenEncrypt,
        protected JwtEncoderInterface $jwtEncoder
    ) {
        $this->sessionTtl = intval($config['token']['session_ttl'] * 60);
        if ($this->sessionTtl < 10) {
            throw new ConfigurationErrorException(
                "Session ttl value cannot be less than 10 minutes"
            );
        }
        $this->tokenValidity = intval($config['token']['token_validity'] * 60);
    }

    /**
     * Generate token data
     * @param  ServerRequestInterface $request    request
     * @param  integer                $expiration integer expiration
     * @return array
     */
    public function generateToken(ServerRequestInterface $request, $expiration = null)
    {
        $result = parent::generateToken($request, $expiration);

        $user = $request->getAttribute(UserInterface::class);
        $details = $user->getDetails();
        $userId = $details['id'];
        $tokenId = $result['data']['meta']['tokenId'];

        $this->cache->getOptions()->setTtl($this->sessionTtl);
        $this->cache->setItem($this->getSessionCacheKey($userId, $tokenId), $this->sessionTtl);

        return $result;
    }

    /**
     * Refresh token
     * 
     * @param  ServerRequestInterface $request    request
     * @param  array                  $decoded    decoded data
     * @param  integer                $expiration int expiration
     * @return bool                   true / false
     */
    public function refreshToken(ServerRequestInterface $request, array $decoded, $expiration = null)
    {
        $userId = $decoded['data']['details']['id'];
        $oldTokenId = $decoded['data']['meta']['tokenId'];

        $sessionTTL = $this->cache->getItem($this->getSessionCacheKey($userId, $oldTokenId));
        if (! $sessionTTL) {
            return false;
        }

        $result = parent::refreshToken($request, $decoded, $expiration);
        if (! $result) {
            return false;
        }

        $tokenId = $result['data']['meta']['tokenId'];
        $this->cache->getOptions()->setTtl($this->sessionTtl);
        $this->cache->setItem($this->getSessionCacheKey($userId, $tokenId), $this->sessionTtl);
        $this->cache->removeItem($this->getSessionCacheKey($userId, $oldTokenId));

        return $result;
    }

    /**
     * Revove user token
     * 
     * @param  string $userId  user id
     * @param  string $tokenId token id
     * @return void
     */
    public function revokeToken(string $userId, string $tokenId)
    {
        parent::revokeToken($userId, $tokenId);

        $this->cache->removeItem($this->getSessionCacheKey($userId, $tokenId));
    }

    /**
     * Returns to session key
     *
     * @return string
     */
    public function getSessionKey(): string
    {
        return APP_CACHE_PREFIX . APP_SESSION_KEY;
    }

    /**
     * Returns to session cache key
     *
     * @param  string $userId  user id
     * @param  string $tokenId token id
     * @return string
     */
    protected function getSessionCacheKey(string $userId, string $tokenId): string
    {
        return $this->getSessionKey() . $userId . ':' . $tokenId;
    }
}
