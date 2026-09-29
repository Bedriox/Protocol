<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

use Bedriox\Protocol\Exception\InvalidValueException;

final readonly class MapPixel
{
    public function __construct(public int $color, public int $index)
    {
        if ($color < -0x80000000 || $color > 0x7fffffff || $index < 0 || $index > 0xffff) {
            throw new InvalidValueException('Map pixel contains an out-of-range value.');
        }
    }
}
