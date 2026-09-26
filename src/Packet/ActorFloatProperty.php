<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

use Bedriox\Protocol\Exception\InvalidValueException;

final readonly class ActorFloatProperty
{
    public function __construct(public int $index, public float $value)
    {
        if ($index < 0 || $index > 0xffffffff) {
            throw new InvalidValueException('Actor float-property index is outside its unsigned 32-bit range.');
        }
        CodecSupport::validateFiniteFloat($value, 'Actor float-property value');
    }
}
