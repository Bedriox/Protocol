<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Codec;

use Bedriox\Protocol\Exception\BufferOverflowException;
use Bedriox\Protocol\Exception\BufferUnderflowException;
use Bedriox\Protocol\Exception\InvalidValueException;
use Bedriox\Protocol\Exception\MalformedDataException;
use Bedriox\Protocol\Value\UnsignedLong;

final readonly class ByteBufferReader
{
    private function __construct(
        private string $bytes,
        private int $offset,
    ) {
    }

    public static function fromString(string $bytes, int $maximumBytes): self
    {
        if ($maximumBytes < 0) {
            throw new InvalidValueException('Reader maximum length cannot be negative.');
        }
        if (strlen($bytes) > $maximumBytes) {
            throw new BufferOverflowException('Input exceeds the configured reader maximum.');
        }

        return new self($bytes, 0);
    }

    public function offset(): int
    {
        return $this->offset;
    }

    public function remaining(): int
    {
        return strlen($this->bytes) - $this->offset;
    }

    public function isAtEnd(): bool
    {
        return $this->remaining() === 0;
    }

    /** @return ReadResult<string> */
    public function readBytes(int $length): ReadResult
    {
        if ($length < 0) {
            throw new InvalidValueException('Read length cannot be negative.');
        }
        if ($length > $this->remaining()) {
            throw new BufferUnderflowException('Requested bytes exceed the remaining input.');
        }

        return new ReadResult(
            substr($this->bytes, $this->offset, $length),
            new self($this->bytes, $this->offset + $length),
        );
    }

    /** @return ReadResult<int> */
    public function readUnsignedByte(): ReadResult
    {
        $read = $this->readBytes(1);
        return new ReadResult(ord($read->value), $read->reader);
    }

    /** @return ReadResult<int> */
    public function readUnsignedShortLE(): ReadResult
    {
        $read = $this->readBytes(2);
        $unpacked = unpack('vvalue', $read->value);
        if ($unpacked === false || !is_int($unpacked['value'])) {
            throw new MalformedDataException('Unable to decode little-endian unsigned short.');
        }
        return new ReadResult($unpacked['value'], $read->reader);
    }

    /** @return ReadResult<int> */
    public function readSignedShortLE(): ReadResult
    {
        $read = $this->readUnsignedShortLE();
        $value = $read->value > 0x7fff ? $read->value - 0x10000 : $read->value;
        return new ReadResult($value, $read->reader);
    }

    /** @return ReadResult<int> */
    public function readUnsignedIntLE(): ReadResult
    {
        $read = $this->readBytes(4);
        $unpacked = unpack('Vvalue', $read->value);
        if ($unpacked === false || !is_int($unpacked['value'])) {
            throw new MalformedDataException('Unable to decode little-endian unsigned integer.');
        }
        return new ReadResult($unpacked['value'], $read->reader);
    }

    /** @return ReadResult<int> */
    public function readSignedIntLE(): ReadResult
    {
        $read = $this->readUnsignedIntLE();
        $value = $read->value > 0x7fffffff ? $read->value - 0x100000000 : $read->value;
        return new ReadResult($value, $read->reader);
    }

    /** @return ReadResult<UnsignedLong> */
    public function readUnsignedLongLE(): ReadResult
    {
        $low = $this->readUnsignedIntLE();
        $high = $low->reader->readUnsignedIntLE();
        return new ReadResult(new UnsignedLong($high->value, $low->value), $high->reader);
    }

    /** @return ReadResult<int> */
    public function readSignedLongLE(): ReadResult
    {
        $read = $this->readUnsignedLongLE();
        return new ReadResult($read->value->toSignedBits(), $read->reader);
    }

    /** @return ReadResult<float> */
    public function readFloatLE(): ReadResult
    {
        $read = $this->readBytes(4);
        $unpacked = unpack('gvalue', $read->value);
        if ($unpacked === false || !is_float($unpacked['value'])) {
            throw new MalformedDataException('Unable to decode little-endian float.');
        }
        return new ReadResult($unpacked['value'], $read->reader);
    }

    /** @return ReadResult<float> */
    public function readDoubleLE(): ReadResult
    {
        $read = $this->readBytes(8);
        $unpacked = unpack('evalue', $read->value);
        if ($unpacked === false || !is_float($unpacked['value'])) {
            throw new MalformedDataException('Unable to decode little-endian double.');
        }
        return new ReadResult($unpacked['value'], $read->reader);
    }

    /** @return ReadResult<int> */
    public function readUnsignedVarInt(): ReadResult
    {
        $decoded = UnsignedVarInt::decode($this->bytes, $this->offset);
        return new ReadResult($decoded['value'], new self($this->bytes, $this->offset + $decoded['bytes']));
    }

    /** @return ReadResult<int> */
    public function readSignedVarInt(): ReadResult
    {
        $decoded = SignedVarInt::decode($this->bytes, $this->offset);
        return new ReadResult($decoded['value'], new self($this->bytes, $this->offset + $decoded['bytes']));
    }

    /** @return ReadResult<UnsignedLong> */
    public function readUnsignedVarLong(): ReadResult
    {
        $decoded = UnsignedVarLong::decode($this->bytes, $this->offset);
        return new ReadResult($decoded['value'], new self($this->bytes, $this->offset + $decoded['bytes']));
    }

    /** @return ReadResult<int> */
    public function readSignedVarLong(): ReadResult
    {
        $decoded = SignedVarLong::decode($this->bytes, $this->offset);
        return new ReadResult($decoded['value'], new self($this->bytes, $this->offset + $decoded['bytes']));
    }

    /** @return ReadResult<string> */
    public function readString(int $maximumBytes): ReadResult
    {
        if ($maximumBytes < 0) {
            throw new InvalidValueException('String maximum length cannot be negative.');
        }

        $length = $this->readUnsignedVarInt();
        if ($length->value > $maximumBytes) {
            throw new MalformedDataException('String exceeds the configured byte limit.');
        }
        $contents = $length->reader->readBytes($length->value);
        if (preg_match('//u', $contents->value) !== 1) {
            throw new MalformedDataException('String is not valid UTF-8.');
        }

        return $contents;
    }
}
