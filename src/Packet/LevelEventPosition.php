<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

final readonly class LevelEventPosition
{
    public function __construct(public float $x, public float $y, public float $z)
    {
        foreach ([$x, $y, $z] as $coordinate) {
            CodecSupport::validateFiniteFloat($coordinate, 'Level-event position');
        }
    }
}
