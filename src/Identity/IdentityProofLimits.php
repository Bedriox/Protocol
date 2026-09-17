<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Identity;

use Bedriox\Protocol\Exception\InvalidValueException;
use Bedriox\Protocol\Security\SecurityLimits;

final readonly class IdentityProofLimits
{
    public SecurityLimits $jws;

    public function __construct(
        ?SecurityLimits $jws = null,
        public int $maximumCertificateJsonBytes = 393_216,
        public int $maximumCertificateJsonTokens = 4_096,
        public int $maximumDisplayNameBytes = 128,
        public int $maximumXuidBytes = 32,
    ) {
        $this->jws = $jws ?? new SecurityLimits();
        if ($maximumCertificateJsonBytes < 2 || $maximumCertificateJsonBytes > 1_048_576
            || $maximumCertificateJsonTokens < 3 || $maximumCertificateJsonTokens > 100_000
            || $maximumDisplayNameBytes < 1 || $maximumDisplayNameBytes > 1_024
            || $maximumXuidBytes < 1 || $maximumXuidBytes > 128) {
            throw new InvalidValueException('Identity-proof limits are outside their hard bounds.');
        }
    }
}
