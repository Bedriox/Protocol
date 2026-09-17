<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Security;

use Bedriox\Protocol\Exception\InvalidValueException;

final readonly class SecurityLimits
{
    public function __construct(
        public int $maximumCompactJwsBytes = 131072,
        public int $maximumHeaderBytes = 4096,
        public int $maximumPayloadBytes = 65536,
        public int $maximumJsonDepth = 16,
        public int $maximumSpkiDerBytes = 2048,
    ) {
        if ($maximumCompactJwsBytes < 3 || $maximumHeaderBytes < 2 || $maximumPayloadBytes < 2
            || $maximumJsonDepth < 2 || $maximumJsonDepth > 64 || $maximumSpkiDerBytes < 1) {
            throw new InvalidValueException('Security limits are outside their supported range');
        }
    }
}
