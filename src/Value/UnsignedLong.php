<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Value;

use Bedriox\Protocol\Exception\InvalidValueException;

/** An unsigned 64-bit value represented as two unsigned 32-bit limbs. */
final readonly class UnsignedLong
{
    public const int MAX_LIMB = 0xffffffff;

    public function __construct(
        public int $high,
        public int $low,
    ) {
        if ($high < 0 || $high > self::MAX_LIMB || $low < 0 || $low > self::MAX_LIMB) {
            throw new InvalidValueException('UnsignedLong limbs must be between 0 and 4294967295.');
        }
    }

    public static function fromInt(int $value): self
    {
        if ($value < 0) {
            throw new InvalidValueException('Cannot create an UnsignedLong from a negative integer.');
        }

        return new self(($value >> 32) & self::MAX_LIMB, $value & self::MAX_LIMB);
    }

    /** Interprets a PHP integer's two's-complement bit pattern as unsigned. */
    public static function fromSignedBits(int $bits): self
    {
        return new self(($bits >> 32) & self::MAX_LIMB, $bits & self::MAX_LIMB);
    }

    public function equals(self $other): bool
    {
        return $this->high === $other->high && $this->low === $other->low;
    }

    /** Compares the full unsigned value without converting it to a signed PHP integer. */
    public function compareTo(self $other): int
    {
        $high = $this->high <=> $other->high;
        return $high !== 0 ? $high : $this->low <=> $other->low;
    }

    /** Returns the identical 64-bit bit pattern as a signed PHP integer. */
    public function toSignedBits(): int
    {
        return ($this->high << 32) | $this->low;
    }
}
