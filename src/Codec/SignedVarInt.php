<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Codec;

use Bedriox\Protocol\Exception\InvalidValueException;

final class SignedVarInt
{
    public const int MIN_VALUE = -0x80000000;
    public const int MAX_VALUE = 0x7fffffff;

    public static function encode(int $value): string
    {
        if ($value < self::MIN_VALUE || $value > self::MAX_VALUE) {
            throw new InvalidValueException('Signed VarInt value must fit in 32 bits.');
        }

        $zigZag = (($value << 1) ^ ($value >> 31)) & UnsignedVarInt::MAX_VALUE;
        return UnsignedVarInt::encode($zigZag);
    }

    /** @return array{value: int, bytes: int} */
    public static function decode(string $bytes, int $offset = 0): array
    {
        $decoded = UnsignedVarInt::decode($bytes, $offset);
        $unsigned = $decoded['value'];
        return [
            'value' => ($unsigned >> 1) ^ -($unsigned & 1),
            'bytes' => $decoded['bytes'],
        ];
    }
}

