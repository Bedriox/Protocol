<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Codec;

use Bedriox\Protocol\Exception\BufferUnderflowException;
use Bedriox\Protocol\Exception\InvalidValueException;
use Bedriox\Protocol\Exception\MalformedDataException;
use Bedriox\Protocol\Value\UnsignedLong;

final class UnsignedVarLong
{
    public const int MAX_BYTES = 10;

    public static function encode(UnsignedLong $value): string
    {
        $high = $value->high;
        $low = $value->low;
        $encoded = '';

        do {
            $byte = $low & 0x7f;
            $nextLow = (($low >> 7) | (($high & 0x7f) << 25)) & UnsignedLong::MAX_LIMB;
            $high >>= 7;
            $low = $nextLow;
            if ($high !== 0 || $low !== 0) {
                $byte |= 0x80;
            }
            $encoded .= chr($byte);
        } while ($high !== 0 || $low !== 0);

        return $encoded;
    }

    /** @return array{value: UnsignedLong, bytes: int} */
    public static function decode(string $bytes, int $offset = 0): array
    {
        if ($offset < 0 || $offset > strlen($bytes)) {
            throw new InvalidValueException('Unsigned VarLong offset is outside the input.');
        }

        $high = 0;
        $low = 0;
        for ($index = 0; $index < self::MAX_BYTES; ++$index) {
            $position = $offset + $index;
            if (!isset($bytes[$position])) {
                throw new BufferUnderflowException('Truncated unsigned VarLong.');
            }
            $byte = ord($bytes[$position]);
            $payload = $byte & 0x7f;
            $shift = $index * 7;
            if ($index === 9 && ($payload & 0x7e) !== 0) {
                throw new MalformedDataException('Unsigned VarLong exceeds 64 bits.');
            }

            if ($shift < 32) {
                $low = ($low | (($payload << $shift) & UnsignedLong::MAX_LIMB)) & UnsignedLong::MAX_LIMB;
                if ($shift > 25) {
                    $high |= $payload >> (32 - $shift);
                }
            } else {
                $high = ($high | (($payload << ($shift - 32)) & UnsignedLong::MAX_LIMB)) & UnsignedLong::MAX_LIMB;
            }

            if (($byte & 0x80) === 0) {
                $value = new UnsignedLong($high, $low);
                if (strlen(self::encode($value)) !== $index + 1) {
                    throw new MalformedDataException('Unsigned VarLong is not canonically encoded.');
                }
                return ['value' => $value, 'bytes' => $index + 1];
            }
        }

        throw new MalformedDataException('Unsigned VarLong is too long.');
    }
}

