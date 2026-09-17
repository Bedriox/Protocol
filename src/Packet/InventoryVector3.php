<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

final readonly class InventoryVector3
{
    public function __construct(public float $x, public float $y, public float $z)
    {
        foreach ([$x, $y, $z] as $coordinate) {
            CodecSupport::validateFiniteFloat($coordinate, 'Inventory transaction vector');
        }
    }
}
