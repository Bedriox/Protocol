<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

use Bedriox\Protocol\Codec\ByteBufferReader;
use Bedriox\Protocol\Codec\ByteBufferWriter;
use Bedriox\Protocol\Exception\CodecException;
use Bedriox\Protocol\Exception\InvalidValueException;

/** Typed and bounded network-NBT representation of a modern two-sided sign block actor. */
final readonly class SignBlockActorData
{
    public const int MAXIMUM_BYTES = 8_192;

    public function __construct(
        public BlockPosition $position,
        public SignTextData $front,
        public SignTextData $back,
        public bool $waxed = false,
        public int $lockedForEditingBy = -1,
        public string $identifier = 'Sign',
    ) {
        if (!in_array($this->identifier, ['Sign', 'HangingSign'], true)) {
            throw new InvalidValueException('Sign block-actor identifier is invalid.');
        }
    }

    public static function decode(string $networkNbt): self
    {
        if (strlen($networkNbt) > self::MAXIMUM_BYTES) {
            throw new InvalidValueException('Sign block-actor data exceeds its byte limit.');
        }
        try {
            $parser = new SignNetworkNbtParser(ByteBufferReader::fromString($networkNbt, NetworkNbtCompound::MAXIMUM_BYTES));
            $root = $parser->root();
            if (!in_array(self::string($root, 'id'), ['Sign', 'HangingSign'], true)) {
                throw new InvalidValueException('Sign block-actor identifier is invalid.');
            }
            return new self(
                new BlockPosition(
                    self::integer($root, 'x', 3),
                    self::integer($root, 'y', 3),
                    self::integer($root, 'z', 3),
                ),
                self::text(self::compound($root, 'FrontText')),
                self::text(self::compound($root, 'BackText')),
                self::optionalBoolean($root, 'IsWaxed', false),
                self::optionalInteger($root, 'LockedForEditingBy', 4, -1),
                self::string($root, 'id'),
            );
        } catch (CodecException $exception) {
            throw new InvalidValueException('Sign block-actor data is malformed.', previous: $exception);
        }
    }

    public function encode(): string
    {
        $writer = CodecSupport::writer()->writeUnsignedByte(10)->writeString('', 0);
        $writer = self::writeText($writer, 'BackText', $this->back);
        $writer = self::writeText($writer, 'FrontText', $this->front)
            ->writeUnsignedByte(1)->writeString('IsWaxed', 16)->writeUnsignedByte($this->waxed ? 1 : 0)
            ->writeUnsignedByte(4)->writeString('LockedForEditingBy', 32)
            ->writeSignedVarLong($this->lockedForEditingBy)
            ->writeUnsignedByte(8)->writeString('id', 16)->writeString($this->identifier, 16)
            ->writeUnsignedByte(3)->writeString('x', 1)->writeSignedVarInt($this->position->x)
            ->writeUnsignedByte(3)->writeString('y', 1)->writeSignedVarInt($this->position->y)
            ->writeUnsignedByte(3)->writeString('z', 1)->writeSignedVarInt($this->position->z)
            ->writeUnsignedByte(0);
        $encoded = $writer->toString();
        NetworkNbtCompound::validate($encoded);
        return $encoded;
    }

    /** @param array<string, array{type: int, value: mixed}> $compound */
    private static function text(array $compound): SignTextData
    {
        return new SignTextData(
            self::string($compound, 'Text'),
            self::optionalInteger($compound, 'SignTextColor', 3, -16777216),
            self::optionalBoolean($compound, 'IgnoreLighting', false),
            self::optionalBoolean($compound, 'PersistFormatting', true),
            self::optionalString($compound, 'TextOwner'),
        );
    }

    private static function writeText(ByteBufferWriter $writer, string $name, SignTextData $text): ByteBufferWriter
    {
        return $writer->writeUnsignedByte(10)->writeString($name, 16)
            ->writeUnsignedByte(1)->writeString('HideGlowOutline', 32)->writeUnsignedByte(0)
            ->writeUnsignedByte(1)->writeString('IgnoreLighting', 32)->writeUnsignedByte($text->glowing ? 1 : 0)
            ->writeUnsignedByte(1)->writeString('PersistFormatting', 32)->writeUnsignedByte($text->persistFormatting ? 1 : 0)
            ->writeUnsignedByte(3)->writeString('SignTextColor', 32)->writeSignedVarInt($text->color)
            ->writeUnsignedByte(8)->writeString('Text', 16)->writeString($text->text, SignTextData::MAXIMUM_TEXT_BYTES)
            ->writeUnsignedByte(8)->writeString('TextOwner', 16)->writeString($text->owner, SignTextData::MAXIMUM_OWNER_BYTES)
            ->writeUnsignedByte(0);
    }

    /** @param array<string, array{type: int, value: mixed}> $compound
     * @return array<string, array{type: int, value: mixed}>
     */
    private static function compound(array $compound, string $key): array
    {
        $tag = $compound[$key] ?? null;
        if ($tag === null || $tag['type'] !== 10 || !is_array($tag['value'])) {
            throw new InvalidValueException(sprintf('Sign block-actor tag %s must be a compound.', $key));
        }
        /** @var array<string, array{type: int, value: mixed}> $value */
        $value = $tag['value'];
        return $value;
    }

    /** @param array<string, array{type: int, value: mixed}> $compound */
    private static function integer(array $compound, string $key, int $type): int
    {
        $tag = $compound[$key] ?? null;
        if ($tag === null || $tag['type'] !== $type || !is_int($tag['value'])) {
            throw new InvalidValueException(sprintf('Sign block-actor tag %s has an invalid type.', $key));
        }
        return $tag['value'];
    }

    /** @param array<string, array{type: int, value: mixed}> $compound */
    private static function optionalInteger(array $compound, string $key, int $type, int $default): int
    {
        return isset($compound[$key]) ? self::integer($compound, $key, $type) : $default;
    }

    /** @param array<string, array{type: int, value: mixed}> $compound */
    private static function string(array $compound, string $key): string
    {
        $tag = $compound[$key] ?? null;
        if ($tag === null || $tag['type'] !== 8 || !is_string($tag['value'])) {
            throw new InvalidValueException(sprintf('Sign block-actor tag %s has an invalid type.', $key));
        }
        return $tag['value'];
    }

    /** @param array<string, array{type: int, value: mixed}> $compound */
    private static function optionalString(array $compound, string $key): string
    {
        return isset($compound[$key]) ? self::string($compound, $key) : '';
    }

    /** @param array<string, array{type: int, value: mixed}> $compound */
    private static function boolean(array $compound, string $key): bool
    {
        $value = self::integer($compound, $key, 1);
        if ($value > 1) {
            throw new InvalidValueException(sprintf('Sign block-actor tag %s is not a boolean.', $key));
        }
        return $value === 1;
    }

    /** @param array<string, array{type: int, value: mixed}> $compound */
    private static function optionalBoolean(array $compound, string $key, bool $default): bool
    {
        return isset($compound[$key]) ? self::boolean($compound, $key) : $default;
    }
}

/** @internal Sign-specific bounded network-NBT decoder. */
final class SignNetworkNbtParser
{
    private const int MAXIMUM_DEPTH = 16;
    private const int MAXIMUM_ENTRIES = 128;
    private const int MAXIMUM_COLLECTION_ITEMS = 128;
    private const int MAXIMUM_STRING_BYTES = 4_096;

    private int $entries = 0;

    public function __construct(private ByteBufferReader $reader) {}

    /** @return array<string, array{type: int, value: mixed}> */
    public function root(): array
    {
        $type = $this->reader->readUnsignedByte();
        if ($type->value !== 10) {
            throw new InvalidValueException('Sign network NBT root must be a compound.');
        }
        $name = $type->reader->readString(self::MAXIMUM_STRING_BYTES);
        $this->reader = $name->reader;
        $value = $this->compound(1);
        CodecSupport::requireEnd($this->reader);
        return $value;
    }

    /** @return array<string, array{type: int, value: mixed}> */
    private function compound(int $depth): array
    {
        $result = [];
        while (true) {
            $type = $this->reader->readUnsignedByte();
            $this->reader = $type->reader;
            if ($type->value === 0) {
                return $result;
            }
            $name = $this->reader->readString(self::MAXIMUM_STRING_BYTES);
            $this->reader = $name->reader;
            if (isset($result[$name->value])) {
                throw new InvalidValueException('Sign network NBT contains a duplicate tag.');
            }
            $result[$name->value] = ['type' => $type->value, 'value' => $this->payload($type->value, $depth + 1)];
        }
    }

    private function payload(int $type, int $depth): mixed
    {
        if ($depth > self::MAXIMUM_DEPTH || ++$this->entries > self::MAXIMUM_ENTRIES) {
            throw new InvalidValueException('Sign network NBT exceeds its structural limits.');
        }
        return match ($type) {
            1 => $this->byte(), 2 => $this->fixedInt(2), 3 => $this->varInt(), 4 => $this->varLong(),
            5 => $this->bytes(4), 6 => $this->bytes(8), 7 => $this->byteArray(), 8 => $this->string(),
            9 => $this->list($depth), 10 => $this->compound($depth), 11 => $this->intArray(), 12 => $this->longArray(),
            default => throw new InvalidValueException('Sign network NBT contains an unknown tag type.'),
        };
    }

    private function byte(): int
    {
        $read = $this->reader->readUnsignedByte();
        $this->reader = $read->reader;
        return $read->value;
    }

    private function fixedInt(int $bytes): string
    {
        return $this->bytes($bytes);
    }

    private function varInt(): int
    {
        $read = $this->reader->readSignedVarInt();
        $this->reader = $read->reader;
        return $read->value;
    }

    private function varLong(): int
    {
        $read = $this->reader->readSignedVarLong();
        $this->reader = $read->reader;
        return $read->value;
    }

    private function string(): string
    {
        $read = $this->reader->readString(self::MAXIMUM_STRING_BYTES);
        $this->reader = $read->reader;
        return $read->value;
    }

    private function bytes(int $length): string
    {
        $read = $this->reader->readBytes($length);
        $this->reader = $read->reader;
        return $read->value;
    }

    private function byteArray(): string
    {
        return $this->bytes($this->count());
    }

    /** @return list<mixed> */
    private function list(int $depth): array
    {
        $type = $this->byte();
        $count = $this->count();
        if ($type > 12 || ($type === 0 && $count !== 0)) {
            throw new InvalidValueException('Sign network NBT list type is invalid.');
        }
        $values = [];
        for ($index = 0; $index < $count; ++$index) {
            $values[] = $this->payload($type, $depth + 1);
        }
        return $values;
    }

    /** @return list<int> */
    private function intArray(): array
    {
        $values = [];
        for ($i = 0, $n = $this->count(); $i < $n; ++$i) {
            $values[] = $this->varInt();
        }
        return $values;
    }

    /** @return list<int> */
    private function longArray(): array
    {
        $values = [];
        for ($i = 0, $n = $this->count(); $i < $n; ++$i) {
            $values[] = $this->varLong();
        }
        return $values;
    }

    private function count(): int
    {
        $count = $this->varInt();
        if ($count < 0 || $count > self::MAXIMUM_COLLECTION_ITEMS) {
            throw new InvalidValueException('Sign network NBT collection is oversized.');
        }
        return $count;
    }
}
