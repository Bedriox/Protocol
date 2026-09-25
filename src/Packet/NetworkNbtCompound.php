<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

use Bedriox\Protocol\Codec\ByteBufferReader;
use Bedriox\Protocol\Exception\CodecException;
use Bedriox\Protocol\Exception\InvalidValueException;

/** Structural validator for exactly one bounded Bedrock network-NBT compound root. */
final class NetworkNbtCompound
{
    public const int MAXIMUM_BYTES = 1_048_576;
    private const int MAXIMUM_DEPTH = 32;
    private const int MAXIMUM_ENTRIES = 65_536;
    private const int MAXIMUM_COLLECTION_ITEMS = 65_536;
    private const int MAXIMUM_STRING_BYTES = 65_536;

    private int $entries = 0;

    private function __construct() {}

    public static function validate(string $networkNbt): void
    {
        if ($networkNbt === '' || strlen($networkNbt) > self::MAXIMUM_BYTES) {
            throw new InvalidValueException('Network NBT compound is empty or oversized.');
        }
        try {
            $reader = ByteBufferReader::fromString($networkNbt, self::MAXIMUM_BYTES);
            $rootType = $reader->readUnsignedByte();
            if ($rootType->value !== 10) {
                throw new InvalidValueException('Network NBT root must be a compound.');
            }
            $rootName = $rootType->reader->readString(self::MAXIMUM_STRING_BYTES);
            $validator = new self();
            $reader = $validator->readPayload($rootName->reader, 10, 1);
            if (!$reader->isAtEnd()) {
                throw new InvalidValueException('Network NBT compound contains trailing data.');
            }
        } catch (CodecException $e) {
            if ($e instanceof InvalidValueException) {
                throw $e;
            }
            throw new InvalidValueException('Network NBT compound is malformed.', previous: $e);
        }
    }

    private function readPayload(ByteBufferReader $reader, int $type, int $depth): ByteBufferReader
    {
        if ($depth > self::MAXIMUM_DEPTH || ++$this->entries > self::MAXIMUM_ENTRIES) {
            throw new InvalidValueException('Network NBT exceeds its structural limits.');
        }
        return match ($type) {
            1 => $reader->readBytes(1)->reader,
            2 => $reader->readBytes(2)->reader,
            3 => $reader->readSignedVarInt()->reader,
            4 => $reader->readSignedVarLong()->reader,
            5 => $reader->readBytes(4)->reader,
            6 => $reader->readBytes(8)->reader,
            7 => $this->readByteArray($reader),
            8 => $reader->readString(self::MAXIMUM_STRING_BYTES)->reader,
            9 => $this->readList($reader, $depth),
            10 => $this->readCompound($reader, $depth),
            11 => $this->readIntArray($reader),
            12 => $this->readLongArray($reader),
            default => throw new InvalidValueException('Network NBT contains an unknown tag type.'),
        };
    }

    private function readCompound(ByteBufferReader $reader, int $depth): ByteBufferReader
    {
        $names = [];
        while (true) {
            $type = $reader->readUnsignedByte();
            $reader = $type->reader;
            if ($type->value === 0) {
                return $reader;
            }
            $name = $reader->readString(self::MAXIMUM_STRING_BYTES);
            if (isset($names[$name->value])) {
                throw new InvalidValueException('Network NBT compound contains a duplicate key.');
            }
            $names[$name->value] = true;
            $reader = $this->readPayload($name->reader, $type->value, $depth + 1);
        }
    }

    private function readList(ByteBufferReader $reader, int $depth): ByteBufferReader
    {
        $type = $reader->readUnsignedByte();
        if ($type->value > 12) {
            throw new InvalidValueException('Network NBT list contains an unknown element type.');
        }
        [$count, $reader] = $this->readCollectionCount($type->reader);
        if ($type->value === 0 && $count !== 0) {
            throw new InvalidValueException('Network NBT cannot contain a non-empty end-tag list.');
        }
        for ($index = 0; $index < $count; ++$index) {
            $reader = $this->readPayload($reader, $type->value, $depth + 1);
        }
        return $reader;
    }

    private function readByteArray(ByteBufferReader $reader): ByteBufferReader
    {
        [$count, $reader] = $this->readCollectionCount($reader);
        return $reader->readBytes($count)->reader;
    }

    private function readIntArray(ByteBufferReader $reader): ByteBufferReader
    {
        [$count, $reader] = $this->readCollectionCount($reader);
        for ($index = 0; $index < $count; ++$index) {
            $reader = $reader->readSignedVarInt()->reader;
        }
        return $reader;
    }

    private function readLongArray(ByteBufferReader $reader): ByteBufferReader
    {
        [$count, $reader] = $this->readCollectionCount($reader);
        for ($index = 0; $index < $count; ++$index) {
            $reader = $reader->readSignedVarLong()->reader;
        }
        return $reader;
    }

    /** @return array{int, ByteBufferReader} */
    private function readCollectionCount(ByteBufferReader $reader): array
    {
        $count = $reader->readSignedVarInt();
        if ($count->value < 0 || $count->value > self::MAXIMUM_COLLECTION_ITEMS) {
            throw new InvalidValueException('Network NBT collection size is outside its limit.');
        }
        return [$count->value, $count->reader];
    }
}
