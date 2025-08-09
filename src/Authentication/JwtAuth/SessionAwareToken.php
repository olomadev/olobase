<?php

declare(strict_types=1);

namespace Olobase\Authentication\JwtAuth;

use Laminas\Cache\Storage\StorageInterface;
use Mezzio\Authentication\UserInterface;
use Olobase\Authentication\Util\TokenEncryptHelper;
use Psr\Http\Message\ServerRequestInterface;

use function intval;

class SessionAwareToken extends StatelessToken
{
    protected $sessionTtl;

    public function __construct(
        array $config,
        protected StorageInterface $cache,
        protected TokenEncryptHelper $tokenEncrypt,
        protected JwtEncoderInterface $jwtEncoder
    ) {
        $this->sessionTtl = intval($config['authentication']['token']['session_ttl'] * 60);
        if ($this->sessionTtl < 10) {
            throw new ConfigurationErrorException(
                "Session ttl value cannot be less than 10 minutes"
            );
        }
        parent::__construct($config, $tokenEncrypt, $jwtEncoder);
    }

    public function generateToken(ServerRequestInterface $request, $expiration = null)
    {
        $result = parent::generateToken($request, $expiration);

        $user    = $request->getAttribute(UserInterface::class);
        $details = $user->getDetails();
        $userId  = $details['id'];
        $tokenId = $result['data']['meta']['tokenId'];

        $this->cache->getOptions()->setTtl($this->sessionTtl);
        $this->cache->setItem($this->getSessionCacheKey($userId, $tokenId), $this->sessionTtl);

        return $result;
    }

    public function refreshToken(ServerRequestInterface $request, array $decoded, $expiration = null)
    {
        $userId     = $decoded['data']['details']['id'];
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

    public function revokeToken(string $userId, string $tokenId)
    {
        parent::revokeToken($userId, $tokenId);

        $this->cache->removeItem($this->getSessionCacheKey($userId, $tokenId));
    }

    public function getSessionKey(): string
    {
        return APP_CACHE_PREFIX . APP_SESSION_KEY;
    }

    protected function getSessionCacheKey(string $userId, string $tokenId): string
    {
        return $this->getSessionKey() . $userId . ':' . $tokenId;
    }
}
