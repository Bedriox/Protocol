<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

use Bedriox\Protocol\Codec\ByteBufferReader;
use Bedriox\Protocol\Codec\ByteBufferWriter;
use Bedriox\Protocol\Exception\InvalidValueException;
use Bedriox\Protocol\Exception\MalformedDataException;

/** One typed Bedrock actor metadata entry. */
final readonly class ActorMetadata
{
    public const int TYPE_BYTE = 0;
    public const int TYPE_SHORT = 1;
    public const int TYPE_INT = 2;
    public const int TYPE_FLOAT = 3;
    public const int TYPE_STRING = 4;
    public const int TYPE_BLOCK_POSITION = 6;
    public const int TYPE_LONG = 7;
    public const int TYPE_VECTOR3 = 8;

    private const int MAX_ID = 1_024;
    private const int MAX_STRING_BYTES = 1_024;

    private function __construct(
        public int $id,
        public int $type,
        public int|float|string|BlockPosition|ActorMetadataVector3 $value,
    ) {
        if ($id < 0 || $id > self::MAX_ID) {
            throw new InvalidValueException('Actor metadata ID is outside its supported range.');
        }
    }

    public static function byte(int $id, int $value): self
    {
        if ($value < 0 || $value > 0xff) {
            throw new InvalidValueException('Actor metadata byte is outside its representable range.');
        }
        return new self($id, self::TYPE_BYTE, $value);
    }

    public static function short(int $id, int $value): self
    {
        if ($value < -0x8000 || $value > 0x7fff) {
            throw new InvalidValueException('Actor metadata short is outside its representable range.');
        }
        return new self($id, self::TYPE_SHORT, $value);
    }

    public static function int(int $id, int $value): self
    {
        if ($value < -0x80000000 || $value > 0x7fffffff) {
            throw new InvalidValueException('Actor metadata integer is outside its representable range.');
        }
        return new self($id, self::TYPE_INT, $value);
    }

    public static function float(int $id, float $value): self
    {
        CodecSupport::validateFiniteFloat($value, 'Actor metadata float');
        return new self($id, self::TYPE_FLOAT, $value);
    }

    public static function string(int $id, string $value): self
    {
        CodecSupport::validateString($value, self::MAX_STRING_BYTES, 'Actor metadata string');
        return new self($id, self::TYPE_STRING, $value);
    }

    public static function long(int $id, int $value): self
    {
        return new self($id, self::TYPE_LONG, $value);
    }

    public static function blockPosition(int $id, BlockPosition $value): self
    {
        return new self($id, self::TYPE_BLOCK_POSITION, $value);
    }

    public static function vector3(int $id, float $x, float $y, float $z): self
    {
        return new self($id, self::TYPE_VECTOR3, new ActorMetadataVector3($x, $y, $z));
    }

    public function write(ByteBufferWriter $writer): ByteBufferWriter
    {
        $writer = $writer->writeUnsignedVarInt($this->id)
            ->writeUnsignedVarInt($this->type)
            ->writeUnsignedByte($this->type);

        return match ($this->type) {
            self::TYPE_BYTE => $writer->writeUnsignedByte($this->integerValue()),
            self::TYPE_SHORT => $writer->writeSignedShortLE($this->integerValue()),
            self::TYPE_INT => $writer->writeSignedVarInt($this->integerValue()),
            self::TYPE_FLOAT => $writer->writeFloatLE($this->floatValue()),
            self::TYPE_STRING => $writer->writeString($this->stringValue(), self::MAX_STRING_BYTES),
            self::TYPE_BLOCK_POSITION => $this->writeBlockPosition($writer),
            self::TYPE_LONG => $writer->writeSignedVarLong($this->integerValue()),
            self::TYPE_VECTOR3 => $this->writeVector3($writer),
            default => throw new InvalidValueException('Actor metadata type is unsupported.'),
        };
    }

    /** @return array{self, ByteBufferReader} */
    public static function read(ByteBufferReader $reader): array
    {
        $id = $reader->readUnsignedVarInt();
        if ($id->value > self::MAX_ID) {
            throw new MalformedDataException('Actor metadata ID exceeds its supported range.');
        }
        $type = $id->reader->readUnsignedVarInt();
        $repeatedType = $type->reader->readUnsignedByte();
        if ($type->value !== $repeatedType->value) {
            throw new MalformedDataException('Actor metadata type markers disagree.');
        }

        return match ($type->value) {
            self::TYPE_BYTE => self::readByte($id->value, $repeatedType->reader),
            self::TYPE_SHORT => self::readShort($id->value, $repeatedType->reader),
            self::TYPE_INT => self::readInt($id->value, $repeatedType->reader),
            self::TYPE_FLOAT => self::readFloat($id->value, $repeatedType->reader),
            self::TYPE_STRING => self::readString($id->value, $repeatedType->reader),
            self::TYPE_BLOCK_POSITION => self::readBlockPosition($id->value, $repeatedType->reader),
            self::TYPE_LONG => self::readLong($id->value, $repeatedType->reader),
            self::TYPE_VECTOR3 => self::readVector3($id->value, $repeatedType->reader),
            default => throw new MalformedDataException('Actor metadata type is unsupported.'),
        };
    }

    /** @return array{self, ByteBufferReader} */
    private static function readByte(int $id, ByteBufferReader $reader): array
    {
        $read = $reader->readUnsignedByte();
        return [self::byte($id, $read->value), $read->reader];
    }

    /** @return array{self, ByteBufferReader} */
    private static function readShort(int $id, ByteBufferReader $reader): array
    {
        $read = $reader->readSignedShortLE();
        return [self::short($id, $read->value), $read->reader];
    }

    /** @return array{self, ByteBufferReader} */
    private static function readInt(int $id, ByteBufferReader $reader): array
    {
        $read = $reader->readSignedVarInt();
        return [self::int($id, $read->value), $read->reader];
    }

    /** @return array{self, ByteBufferReader} */
    private static function readFloat(int $id, ByteBufferReader $reader): array
    {
        $read = $reader->readFloatLE();
        CodecSupport::validateFiniteFloat($read->value, 'Actor metadata float', true);
        return [self::float($id, $read->value), $read->reader];
    }

    /** @return array{self, ByteBufferReader} */
    private static function readString(int $id, ByteBufferReader $reader): array
    {
        $read = $reader->readString(self::MAX_STRING_BYTES);
        return [self::string($id, $read->value), $read->reader];
    }

    /** @return array{self, ByteBufferReader} */
    private static function readLong(int $id, ByteBufferReader $reader): array
    {
        $read = $reader->readSignedVarLong();
        return [self::long($id, $read->value), $read->reader];
    }

    /** @return array{self, ByteBufferReader} */
    private static function readBlockPosition(int $id, ByteBufferReader $reader): array
    {
        $x = $reader->readSignedVarInt();
        $y = $x->reader->readSignedVarInt();
        $z = $y->reader->readSignedVarInt();

        return [self::blockPosition($id, new BlockPosition($x->value, $y->value, $z->value)), $z->reader];
    }

    /** @return array{self, ByteBufferReader} */
    private static function readVector3(int $id, ByteBufferReader $reader): array
    {
        $x = $reader->readFloatLE();
        $y = $x->reader->readFloatLE();
        $z = $y->reader->readFloatLE();
        foreach ([$x->value, $y->value, $z->value] as $coordinate) {
            CodecSupport::validateFiniteFloat($coordinate, 'Actor metadata vector', true);
        }
        return [self::vector3($id, $x->value, $y->value, $z->value), $z->reader];
    }

    private function writeVector3(ByteBufferWriter $writer): ByteBufferWriter
    {
        if (!$this->value instanceof ActorMetadataVector3) {
            throw new InvalidValueException('Actor metadata value is not a vector.');
        }
        return $writer->writeFloatLE($this->value->x)
            ->writeFloatLE($this->value->y)
            ->writeFloatLE($this->value->z);
    }

    private function writeBlockPosition(ByteBufferWriter $writer): ByteBufferWriter
    {
        if (!$this->value instanceof BlockPosition) {
            throw new InvalidValueException('Actor metadata value is not a block position.');
        }

        return $writer->writeSignedVarInt($this->value->x)
            ->writeSignedVarInt($this->value->y)
            ->writeSignedVarInt($this->value->z);
    }

    private function integerValue(): int
    {
        if (!is_int($this->value)) {
            throw new InvalidValueException('Actor metadata value is not an integer.');
        }
        return $this->value;
    }

    private function floatValue(): float
    {
        if (!is_float($this->value)) {
            throw new InvalidValueException('Actor metadata value is not a float.');
        }
        return $this->value;
    }

    private function stringValue(): string
    {
        if (!is_string($this->value)) {
            throw new InvalidValueException('Actor metadata value is not a string.');
        }
        return $this->value;
    }
}
