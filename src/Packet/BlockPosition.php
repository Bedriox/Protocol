<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

use Bedriox\Protocol\Exception\InvalidValueException;

final readonly class BlockPosition
{
    public function __construct(public int $x, public int $y, public int $z)
    {
        foreach ([$x, $y, $z] as $coordinate) {
            if ($coordinate < -0x80000000 || $coordinate > 0x7fffffff) {
                throw new InvalidValueException('Block position coordinate must fit a signed 32-bit integer.');
            }
        }
    }
}
