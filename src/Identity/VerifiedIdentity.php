<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Identity;

use OpenSSLAsymmetricKey;

final readonly class VerifiedIdentity
{
    public function __construct(
        public string $displayName,
        public string $identity,
        public string $xuid,
        public OpenSSLAsymmetricKey $identityPublicKey,
        public CertificateChainMode $mode,
    ) {
    }
}
