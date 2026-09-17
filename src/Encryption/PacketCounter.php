<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Encryption;

use OverflowException;
use Bedriox\Protocol\Exception\InvalidValueException;

/** Unsigned 64-bit little-endian packet counter. */
final class PacketCounter
{
    private bool $exhausted = false;

    public function __construct(private int $high = 0, private int $low = 0)
    {
        if ($high < 0 || $high > 0xffffffff || $low < 0 || $low > 0xffffffff) {
            throw new InvalidValueException('Packet-counter limbs must be unsigned 32-bit integers.');
        }
    }

    /** Returns the current counter bytes and advances exactly once. */
    public function consumeLittleEndian(): string
    {
        if ($this->exhausted) {
            throw new OverflowException('Encryption packet counter is exhausted.');
        }
        $encoded = pack('V2', $this->low, $this->high);
        if ($this->high === 0xffffffff && $this->low === 0xffffffff) {
            $this->exhausted = true;
            return $encoded;
        }
        if ($this->low === 0xffffffff) {
            $this->low = 0;
            ++$this->high;
        } else {
            ++$this->low;
        }
        return $encoded;
    }
}
