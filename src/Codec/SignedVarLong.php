<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Codec;

use Bedriox\Protocol\Value\UnsignedLong;

final class SignedVarLong
{
    public static function encode(int $value): string
    {
        $zigZagBits = ($value << 1) ^ ($value >> 63);
        return UnsignedVarLong::encode(UnsignedLong::fromSignedBits($zigZagBits));
    }

    /** @return array{value: int, bytes: int} */
    public static function decode(string $bytes, int $offset = 0): array
    {
        $decoded = UnsignedVarLong::decode($bytes, $offset);
        $unsigned = $decoded['value'];
        $shifted = new UnsignedLong(
            $unsigned->high >> 1,
            (($unsigned->low >> 1) | (($unsigned->high & 1) << 31)) & UnsignedLong::MAX_LIMB,
        );
        $bits = $shifted->toSignedBits();
        if (($unsigned->low & 1) !== 0) {
            $bits = ~$bits;
        }

        return ['value' => $bits, 'bytes' => $decoded['bytes']];
    }
}

