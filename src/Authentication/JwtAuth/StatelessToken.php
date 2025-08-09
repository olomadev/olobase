<?php

declare(strict_types=1);

namespace Olobase\Authentication\JwtAuth;

use Olobase\Authentication\Util\TokenEncryptHelper;

class StatelessToken extends AbstractToken
{
    public function __construct(
        array $config,
        protected TokenEncryptHelper $tokenEncrypt,
        protected JwtEncoderInterface $jwtEncoder
    ) {
        parent::__construct($config, $tokenEncrypt, $jwtEncoder);
    }
}
