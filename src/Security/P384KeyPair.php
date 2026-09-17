<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Security;

use OpenSSLAsymmetricKey;

final readonly class P384KeyPair
{
    public function __construct(
        public OpenSSLAsymmetricKey $privateKey,
        public OpenSSLAsymmetricKey $publicKey,
    ) {
        P384::assertKeyPair($privateKey, $publicKey);
    }
}
