<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Codec;

use Bedriox\Protocol\Exception\BufferUnderflowException;
use Bedriox\Protocol\Exception\InvalidValueException;
use Bedriox\Protocol\Exception\MalformedDataException;

final class UnsignedVarInt
{
    public const int MAX_BYTES = 5;
    public const int MAX_VALUE = 0xffffffff;

    public static function encode(int $value): string
    {
        if ($value < 0 || $value > self::MAX_VALUE) {
            throw new InvalidValueException('Unsigned VarInt value must be between 0 and 4294967295.');
        }

        $encoded = '';
        do {
            $byte = $value & 0x7f;
            $value >>= 7;
            if ($value !== 0) {
                $byte |= 0x80;
            }
            $encoded .= chr($byte);
        } while ($value !== 0);

        return $encoded;
    }

    /** @return array{value: int, bytes: int} */
    public static function decode(string $bytes, int $offset = 0): array
    {
        if ($offset < 0 || $offset > strlen($bytes)) {
            throw new InvalidValueException('Unsigned VarInt offset is outside the input.');
        }

        $value = 0;
        for ($index = 0; $index < self::MAX_BYTES; ++$index) {
            $position = $offset + $index;
            if (!isset($bytes[$position])) {
                throw new BufferUnderflowException('Truncated unsigned VarInt.');
            }

            $byte = ord($bytes[$position]);
            if ($index === 4 && ($byte & 0xf0) !== 0) {
                throw new MalformedDataException('Unsigned VarInt exceeds 32 bits.');
            }

            $value |= ($byte & 0x7f) << ($index * 7);
            if (($byte & 0x80) === 0) {
                if (strlen(self::encode($value)) !== $index + 1) {
                    throw new MalformedDataException('Unsigned VarInt is not canonically encoded.');
                }
                return ['value' => $value, 'bytes' => $index + 1];
            }
        }

        throw new MalformedDataException('Unsigned VarInt is too long.');
    }
}
