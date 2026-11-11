<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

use Bedriox\Protocol\Exception\InvalidValueException;

/** Immutable map colors in their native little-endian RGBA wire representation. */
final readonly class PackedMapColors
{
    public int $count;

    public function __construct(public string $bytes)
    {
        $length = strlen($bytes);
        if ($length % 4 !== 0) {
            throw new InvalidValueException('Packed map colors must contain complete RGBA values.');
        }
        $this->count = intdiv($length, 4);
        if ($this->count > ClientboundMapItemDataPacket::MAXIMUM_COLORS) {
            throw new InvalidValueException('Packed map color data exceeds its limit.');
        }
    }
}
