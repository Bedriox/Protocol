<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

/** Canonical conversion between authoritative feet Y and Bedrock's player wire Y. */
final class PlayerPositionProjection
{
    public const float EYE_OFFSET = 1.621;

    public static function feetToWireY(float $feetY): float
    {
        CodecSupport::validateFiniteFloat($feetY, 'Feet Y');
        return $feetY + self::EYE_OFFSET;
    }

    public static function wireToFeetY(float $wireY): float
    {
        CodecSupport::validateFiniteFloat($wireY, 'Wire Y');
        return $wireY - self::EYE_OFFSET;
    }
}
