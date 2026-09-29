<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Value;

use Bedriox\Protocol\Exception\InvalidValueException;

/** A block-network hash in its canonical signed 32-bit representation. */
final readonly class BlockNetworkId
{
    private function __construct(private int $signed) {}

    public static function fromSigned(int $value): self
    {
        if ($value < -0x80000000 || $value > 0x7fffffff) {
            throw new InvalidValueException('Block network ID must fit a signed 32-bit integer.');
        }
        return new self($value);
    }

    public static function fromUnsigned(int $value): self
    {
        if ($value < 0 || $value > 0xffffffff) {
            throw new InvalidValueException('Block network ID must fit an unsigned 32-bit integer.');
        }
        return new self($value > 0x7fffffff ? $value - 0x100000000 : $value);
    }

    public function signed(): int { return $this->signed; }

    public function unsigned(): int
    {
        return $this->signed < 0 ? $this->signed + 0x100000000 : $this->signed;
    }
}
