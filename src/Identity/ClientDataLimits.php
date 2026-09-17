<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Identity;

use Bedriox\Protocol\Exception\InvalidValueException;
use Bedriox\Protocol\Security\SecurityLimits;

final readonly class ClientDataLimits
{
    public SecurityLimits $jws;

    public function __construct(
        ?SecurityLimits $jws = null,
        public int $maximumDimension = 8_192,
        public int $maximumSkinBytes = 262_144,
        public int $maximumCapeBytes = 65_536,
        public int $maximumGeometryBytes = 65_536,
        public int $maximumAnimations = 16,
        public int $maximumAggregateDecodedBytes = 524_288,
        public int $maximumJsonTokens = 16_384,
        public int $maximumGeometryJsonTokens = 8_192,
    ) {
        $this->jws = $jws ?? new SecurityLimits(
            maximumCompactJwsBytes: 1_048_576,
            maximumPayloadBytes: 780_000,
        );
        if ($maximumDimension < 1 || $maximumDimension > 16_384
            || $maximumSkinBytes < 1 || $maximumSkinBytes > 16_777_216
            || $maximumCapeBytes < 1 || $maximumCapeBytes > 16_777_216
            || $maximumGeometryBytes < 1 || $maximumGeometryBytes > 16_777_216
            || $maximumAnimations < 1 || $maximumAnimations > 256
            || $maximumAggregateDecodedBytes < 1 || $maximumAggregateDecodedBytes > 33_554_432
            || $maximumJsonTokens < 1 || $maximumJsonTokens > 1_000_000
            || $maximumGeometryJsonTokens < 1 || $maximumGeometryJsonTokens > 1_000_000
            || $maximumSkinBytes > $maximumAggregateDecodedBytes
            || $maximumCapeBytes > $maximumAggregateDecodedBytes
            || $maximumGeometryBytes > $maximumAggregateDecodedBytes
            || intdiv($maximumAggregateDecodedBytes + 2, 3) * 4 > $this->jws->maximumPayloadBytes) {
            throw new InvalidValueException('Client-data limits are outside their hard bounds.');
        }
    }
}
