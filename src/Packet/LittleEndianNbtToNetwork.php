<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

use Bedriox\Protocol\Codec\SignedVarInt;
use Bedriox\Protocol\Codec\SignedVarLong;
use Bedriox\Protocol\Codec\UnsignedVarInt;
use Bedriox\Protocol\Exception\InvalidValueException;

/** Converts one bounded little-endian NBT root from Bedriox/Data into Bedrock network NBT. */
final class LittleEndianNbtToNetwork
{
    private int $offset = 0;
    private int $entries = 0;

    private function __construct(private readonly string $input) {}

    public static function convert(string $input): string
    {
        if ($input === '' || strlen($input) > 262_144) {
            throw new InvalidValueException('Component NBT is empty or oversized.');
        }
        $c = new self($input);
        $result = $c->namedTag(0);
        if ($c->offset !== strlen($input) || ord($result[0]) !== 10) {
            throw new InvalidValueException('Component NBT must contain exactly one root compound.');
        }
        return $result;
    }

    private function namedTag(int $depth): string
    {
        $type = $this->byte();
        if ($type === 0) { throw new InvalidValueException('Named NBT tag cannot be an end tag.'); }
        $name = $this->leString();
        return chr($type & 0xff) . UnsignedVarInt::encode(strlen($name)) . $name . $this->payload($type, $depth + 1);
    }

    private function payload(int $type, int $depth): string
    {
        if ($depth > 16 || ++$this->entries > 1_000_000) { throw new InvalidValueException('Component NBT exceeds its structural limits.'); }
        return match ($type) {
            1 => $this->read(1), 2 => $this->read(2),
            3 => SignedVarInt::encode($this->int32()), 4 => SignedVarLong::encode($this->int64()),
            5 => $this->read(4), 6 => $this->read(8),
            7 => $this->byteArray(),
            8 => $this->stringPayload(),
            9 => $this->listPayload($depth), 10 => $this->compoundPayload($depth),
            11 => $this->intArray(), 12 => $this->longArray(),
            default => throw new InvalidValueException('Component NBT contains an unknown tag type.'),
        };
    }

    private function compoundPayload(int $depth): string
    {
        $output = ''; $names = [];
        while (($type = $this->byte()) !== 0) {
            $name = $this->leString();
            if (isset($names[$name])) { throw new InvalidValueException('Component NBT contains a duplicate compound key.'); }
            $names[$name] = true;
            $output .= chr($type & 0xff) . UnsignedVarInt::encode(strlen($name)) . $name . $this->payload($type, $depth + 1);
        }
        return $output . "\0";
    }

    private function listPayload(int $depth): string
    {
        $type = $this->byte(); $count = $this->length();
        if ($type > 12) { throw new InvalidValueException('Component NBT list has an unknown element type.'); }
        if ($type === 0 && $count !== 0) { throw new InvalidValueException('Component NBT has a non-empty end-tag list.'); }
        $output = chr($type & 0xff) . SignedVarInt::encode($count);
        for ($i = 0; $i < $count; ++$i) { $output .= $this->payload($type, $depth + 1); }
        return $output;
    }

    private function intArray(): string
    {
        $count = $this->length(); $output = SignedVarInt::encode($count);
        for ($i = 0; $i < $count; ++$i) { $output .= SignedVarInt::encode($this->int32()); }
        return $output;
    }

    private function byteArray(): string
    {
        $length = $this->length();
        return SignedVarInt::encode($length) . $this->read($length);
    }

    private function stringPayload(): string
    {
        $value = $this->leString();
        return UnsignedVarInt::encode(strlen($value)) . $value;
    }

    private function longArray(): string
    {
        $count = $this->length(); $output = SignedVarInt::encode($count);
        for ($i = 0; $i < $count; ++$i) { $output .= SignedVarLong::encode($this->int64()); }
        return $output;
    }

    private function length(): int
    {
        $n = $this->int32();
        if ($n < 0 || $n > 65_536) { throw new InvalidValueException('Component NBT collection is oversized.'); }
        return $n;
    }

    private function leString(): string
    {
        $u = unpack('vvalue', $this->read(2));
        if ($u === false || !is_int($u['value']) || $u['value'] > 4_096) { throw new InvalidValueException('Component NBT string is oversized.'); }
        $s = $this->read($u['value']);
        if (preg_match('//u', $s) !== 1) { throw new InvalidValueException('Component NBT string is invalid UTF-8.'); }
        return $s;
    }

    private function int32(): int
    {
        $u = unpack('Vvalue', $this->read(4));
        if ($u === false || !is_int($u['value'])) { throw new InvalidValueException('Component NBT integer cannot be decoded.'); }
        return $u['value'] > 0x7fffffff ? $u['value'] - 0x100000000 : $u['value'];
    }

    private function int64(): int
    {
        $u = unpack('Vlow/Vhigh', $this->read(8));
        if ($u === false || !is_int($u['low']) || !is_int($u['high'])) { throw new InvalidValueException('Component NBT long cannot be decoded.'); }
        return ($u['high'] << 32) | $u['low'];
    }

    private function byte(): int { return ord($this->read(1)); }
    private function read(int $length): string
    {
        if ($this->offset + $length > strlen($this->input)) { throw new InvalidValueException('Component NBT is truncated.'); }
        $result = substr($this->input, $this->offset, $length); $this->offset += $length; return $result;
    }
}
