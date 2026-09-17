<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Codec;

use Bedriox\Protocol\Exception\BufferOverflowException;
use Bedriox\Protocol\Exception\InvalidValueException;
use Bedriox\Protocol\Value\UnsignedLong;

final readonly class ByteBufferWriter
{
    private function __construct(
        private string $bytes,
        private int $maximumBytes,
    ) {
    }

    public static function withCapacity(int $maximumBytes): self
    {
        if ($maximumBytes < 0) {
            throw new InvalidValueException('Writer maximum length cannot be negative.');
        }
        return new self('', $maximumBytes);
    }

    public function length(): int
    {
        return strlen($this->bytes);
    }

    public function toString(): string
    {
        return $this->bytes;
    }

    public function writeBytes(string $bytes): self
    {
        if (strlen($bytes) > $this->maximumBytes - strlen($this->bytes)) {
            throw new BufferOverflowException('Write exceeds the configured buffer maximum.');
        }
        return new self($this->bytes . $bytes, $this->maximumBytes);
    }

    public function writeUnsignedByte(int $value): self
    {
        if ($value < 0 || $value > 0xff) {
            throw new InvalidValueException('Unsigned byte is outside its representable range.');
        }

        return $this->writeBytes(chr($value));
    }

    public function writeUnsignedShortLE(int $value): self
    {
        self::requireRange($value, 0, 0xffff, 'Unsigned short');
        return $this->writeBytes(pack('v', $value));
    }

    public function writeSignedShortLE(int $value): self
    {
        self::requireRange($value, -0x8000, 0x7fff, 'Signed short');
        return $this->writeBytes(pack('v', $value & 0xffff));
    }

    public function writeUnsignedIntLE(int $value): self
    {
        self::requireRange($value, 0, 0xffffffff, 'Unsigned integer');
        return $this->writeBytes(pack('V', $value));
    }

    public function writeSignedIntLE(int $value): self
    {
        self::requireRange($value, -0x80000000, 0x7fffffff, 'Signed integer');
        return $this->writeBytes(pack('V', $value & 0xffffffff));
    }

    public function writeUnsignedLongLE(UnsignedLong $value): self
    {
        return $this->writeBytes(pack('V', $value->low) . pack('V', $value->high));
    }

    public function writeSignedLongLE(int $value): self
    {
        return $this->writeUnsignedLongLE(UnsignedLong::fromSignedBits($value));
    }

    public function writeFloatLE(float $value): self
    {
        return $this->writeBytes(pack('g', $value));
    }

    public function writeDoubleLE(float $value): self
    {
        return $this->writeBytes(pack('e', $value));
    }

    public function writeUnsignedVarInt(int $value): self
    {
        return $this->writeBytes(UnsignedVarInt::encode($value));
    }

    public function writeSignedVarInt(int $value): self
    {
        return $this->writeBytes(SignedVarInt::encode($value));
    }

    public function writeUnsignedVarLong(UnsignedLong $value): self
    {
        return $this->writeBytes(UnsignedVarLong::encode($value));
    }

    public function writeSignedVarLong(int $value): self
    {
        return $this->writeBytes(SignedVarLong::encode($value));
    }

    public function writeString(string $value, int $maximumBytes): self
    {
        if ($maximumBytes < 0) {
            throw new InvalidValueException('String maximum length cannot be negative.');
        }
        if (preg_match('//u', $value) !== 1) {
            throw new InvalidValueException('String is not valid UTF-8.');
        }
        if (strlen($value) > $maximumBytes) {
            throw new InvalidValueException('String exceeds the configured byte limit.');
        }

        return $this->writeBytes(UnsignedVarInt::encode(strlen($value)) . $value);
    }

    private static function requireRange(int $value, int $minimum, int $maximum, string $type): void
    {
        if ($value < $minimum || $value > $maximum) {
            throw new InvalidValueException($type . ' is outside its representable range.');
        }
    }
}
