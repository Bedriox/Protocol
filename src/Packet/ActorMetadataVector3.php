<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

/** Immutable finite three-float value used by vector actor metadata. */
final readonly class ActorMetadataVector3
{
    public function __construct(public float $x, public float $y, public float $z)
    {
        foreach ([$x, $y, $z] as $coordinate) {
            CodecSupport::validateFiniteFloat($coordinate, 'Actor metadata vector');
        }
    }
}
