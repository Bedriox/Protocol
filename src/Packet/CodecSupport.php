<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

use Bedriox\Protocol\Codec\ByteBufferReader;
use Bedriox\Protocol\Codec\ByteBufferWriter;
use Bedriox\Protocol\Exception\InvalidValueException;
use Bedriox\Protocol\Exception\MalformedDataException;

final class CodecSupport
{
    public const int MAX_PACKET_BYTES = 4_194_304;
    public const int MAX_JWT_BYTES = 1_048_576;
    public const int MAX_SHORT_STRING_BYTES = 4_096;
    public const int MAX_PACKS = 256;
    public const int MAX_EXPERIMENTS = 128;
    public const int MAX_CHAT_BYTES = 512;
    public const int MAX_PLAYER_NAME_BYTES = 64;
    public const int MAX_PLAYER_LIST_ENTRIES = 256;
    public const int MAX_CHUNK_BYTES = 2_097_152;
    public const int MAX_SAVED_CHUNKS = 1_024;

    public static function reader(string $bytes): ByteBufferReader
    {
        return ByteBufferReader::fromString($bytes, self::MAX_PACKET_BYTES);
    }

    public static function writer(): ByteBufferWriter
    {
        return ByteBufferWriter::withCapacity(self::MAX_PACKET_BYTES);
    }

    public static function requireEnd(ByteBufferReader $reader): void
    {
        if (!$reader->isAtEnd()) {
            throw new MalformedDataException('Packet payload contains trailing bytes.');
        }
    }

    /** @return array{bool, ByteBufferReader} */
    public static function readBoolean(ByteBufferReader $reader): array
    {
        $read = $reader->readUnsignedByte();
        if ($read->value > 1) {
            throw new MalformedDataException('Boolean must be encoded as 0 or 1.');
        }
        return [$read->value === 1, $read->reader];
    }

    public static function writeBoolean(ByteBufferWriter $writer, bool $value): ByteBufferWriter
    {
        return $writer->writeUnsignedByte($value ? 1 : 0);
    }

    /** @return array{int, ByteBufferReader} */
    public static function readSignedIntBE(ByteBufferReader $reader): array
    {
        $read = $reader->readBytes(4);
        $value = unpack('Nvalue', $read->value);
        if ($value === false || !is_int($value['value'])) {
            throw new MalformedDataException('Unable to decode big-endian integer.');
        }
        $signed = $value['value'] > 0x7fffffff ? $value['value'] - 0x100000000 : $value['value'];
        return [$signed, $read->reader];
    }

    public static function writeSignedIntBE(ByteBufferWriter $writer, int $value): ByteBufferWriter
    {
        if ($value < -0x80000000 || $value > 0x7fffffff) {
            throw new InvalidValueException('Big-endian integer must fit in 32 bits.');
        }
        return $writer->writeBytes(pack('N', $value & 0xffffffff));
    }

    public static function validateString(string $value, int $maximumBytes, string $field): void
    {
        if (preg_match('//u', $value) !== 1) {
            throw new InvalidValueException($field . ' is not valid UTF-8.');
        }
        if (strlen($value) > $maximumBytes) {
            throw new InvalidValueException($field . ' exceeds its byte limit.');
        }
    }

    public static function validateWireString(string $value, int $maximumBytes, string $field): void
    {
        if (preg_match('//u', $value) !== 1) {
            throw new MalformedDataException($field . ' is not valid UTF-8.');
        }
        if (strlen($value) > $maximumBytes) {
            throw new MalformedDataException($field . ' exceeds its byte limit.');
        }
    }

    public static function validateFiniteFloat(float $value, string $field, bool $wire = false): void
    {
        if (!is_finite($value) || abs($value) > 3.4028234663852886e38) {
            $exception = $wire ? MalformedDataException::class : InvalidValueException::class;
            throw new $exception($field . ' must be a finite 32-bit float.');
        }
    }

    /** @param array<array-key, mixed> $values */
    public static function validateCount(array $values, int $maximum, string $field): void
    {
        if (count($values) > $maximum || !array_is_list($values)) {
            throw new InvalidValueException($field . ' must be a bounded list.');
        }
    }

    public static function uuidToWire(string $uuid): string
    {
        if (preg_match('/^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}$/D', $uuid) !== 1) {
            throw new InvalidValueException('UUID must use canonical hexadecimal form.');
        }
        $bytes = hex2bin(str_replace('-', '', $uuid));
        if ($bytes === false) {
            throw new InvalidValueException('UUID hexadecimal data is invalid.');
        }
        return strrev(substr($bytes, 0, 8)) . strrev(substr($bytes, 8, 8));
    }

    public static function uuidFromWire(string $wire): string
    {
        if (strlen($wire) !== 16) {
            throw new MalformedDataException('UUID wire value must contain 16 bytes.');
        }
        $hex = bin2hex(strrev(substr($wire, 0, 8)) . strrev(substr($wire, 8, 8)));
        return substr($hex, 0, 8) . '-' . substr($hex, 8, 4) . '-' . substr($hex, 12, 4) . '-'
            . substr($hex, 16, 4) . '-' . substr($hex, 20, 12);
    }

    /** @return array{list<ResourcePackStackEntry>, ByteBufferReader} */
    public static function readStackEntries(ByteBufferReader $reader): array
    {
        $count = $reader->readUnsignedVarInt();
        if ($count->value > self::MAX_PACKS) {
            throw new MalformedDataException('Resource-pack stack count exceeds its limit.');
        }
        $entries = [];
        $reader = $count->reader;
        for ($index = 0; $index < $count->value; ++$index) {
            $id = $reader->readString(self::MAX_SHORT_STRING_BYTES);
            $version = $id->reader->readString(self::MAX_SHORT_STRING_BYTES);
            $subPack = $version->reader->readString(self::MAX_SHORT_STRING_BYTES);
            $entries[] = new ResourcePackStackEntry($id->value, $version->value, $subPack->value);
            $reader = $subPack->reader;
        }
        return [$entries, $reader];
    }

    /** @param list<ResourcePackStackEntry> $entries */
    public static function writeStackEntries(ByteBufferWriter $writer, array $entries): ByteBufferWriter
    {
        self::validateCount($entries, self::MAX_PACKS, 'Resource-pack stack');
        $writer = $writer->writeUnsignedVarInt(count($entries));
        foreach ($entries as $entry) {
            $writer = $writer->writeString($entry->packId, self::MAX_SHORT_STRING_BYTES)
                ->writeString($entry->packVersion, self::MAX_SHORT_STRING_BYTES)
                ->writeString($entry->subPackName, self::MAX_SHORT_STRING_BYTES);
        }
        return $writer;
    }
}
