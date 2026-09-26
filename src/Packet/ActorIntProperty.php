<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

use Bedriox\Protocol\Exception\InvalidValueException;

final readonly class ActorIntProperty
{
    public function __construct(public int $index, public int $value)
    {
        if ($index < 0 || $index > 0xffffffff) {
            throw new InvalidValueException('Actor integer-property index is outside its unsigned 32-bit range.');
        }
        if ($value < -0x80000000 || $value > 0x7fffffff) {
            throw new InvalidValueException('Actor integer-property value is outside its signed 32-bit range.');
        }
    }
}
