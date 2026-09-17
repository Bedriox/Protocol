<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

use Bedriox\Protocol\Exception\InvalidValueException;

final readonly class ChunkPosition
{
    public function __construct(public int $x, public int $z)
    {
        foreach ([$x, $z] as $value) {
            if ($value < -0x80000000 || $value > 0x7fffffff) {
                throw new InvalidValueException('Chunk coordinate must fit in a signed 32-bit integer.');
            }
        }
    }
}
