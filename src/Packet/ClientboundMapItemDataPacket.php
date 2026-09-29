<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

use Bedriox\Protocol\Codec\ByteBufferReader;
use Bedriox\Protocol\Codec\ByteBufferWriter;
use Bedriox\Protocol\Exception\InvalidValueException;
use Bedriox\Protocol\Exception\MalformedDataException;

final readonly class ClientboundMapItemDataPacket implements Packet
{
    public const int MAXIMUM_TRACKED_OBJECTS = 1_024;
    public const int MAXIMUM_DECORATIONS = 1_024;
    public const int MAXIMUM_COLORS = 16_384;

    /** @var null|list<int> */
    public ?array $trackedEntityIds;
    /** @var null|list<MapTrackedObject> */
    public ?array $trackedObjects;
    /** @var null|list<MapDecoration> */
    public ?array $decorations;
    /** @var null|list<int> */
    public ?array $colors;

    /**
     * @param null|list<int> $trackedEntityIds
     * @param null|list<MapTrackedObject> $trackedObjects
     * @param null|list<MapDecoration> $decorations
     * @param null|list<int> $colors
     */
    public function __construct(
        public int $uniqueMapId,
        public int $dimensionId,
        public bool $locked,
        public BlockPosition $origin,
        ?array $trackedEntityIds = null,
        public ?int $scale = null,
        ?array $trackedObjects = null,
        ?array $decorations = null,
        public ?int $width = null,
        public ?int $height = null,
        public ?int $xOffset = null,
        public ?int $yOffset = null,
        ?array $colors = null,
    ) {
        if ($dimensionId < 0 || $dimensionId > 0xff || ($scale !== null && ($scale < -0x80 || $scale > 0x7f))) {
            throw new InvalidValueException('Map data contains an out-of-range dimension or scale.');
        }
        if ($trackedEntityIds !== null) {
            if (!array_is_list($trackedEntityIds) || count($trackedEntityIds) > self::MAXIMUM_TRACKED_OBJECTS) {
                throw new InvalidValueException('Map tracked-entity list must be bounded.');
            }
            foreach ($trackedEntityIds as $entityId) {
                if (!is_int($entityId)) {
                    throw new InvalidValueException('Map tracked-entity list contains an invalid value.');
                }
            }
        }
        $this->trackedEntityIds = $trackedEntityIds;
        if ($trackedObjects !== null) {
            if (!array_is_list($trackedObjects) || count($trackedObjects) > self::MAXIMUM_TRACKED_OBJECTS) {
                throw new InvalidValueException('Map tracked-object list must be bounded.');
            }
            foreach ($trackedObjects as $object) {
                if (!$object instanceof MapTrackedObject) {
                    throw new InvalidValueException('Map tracked-object list contains an invalid value.');
                }
            }
        }
        $this->trackedObjects = $trackedObjects;
        if ($decorations !== null) {
            if (!array_is_list($decorations) || count($decorations) > self::MAXIMUM_DECORATIONS) {
                throw new InvalidValueException('Map decoration list must be bounded.');
            }
            foreach ($decorations as $decoration) {
                if (!$decoration instanceof MapDecoration) {
                    throw new InvalidValueException('Map decoration list contains an invalid value.');
                }
            }
        }
        $this->decorations = $decorations;
        if ($colors !== null && (!array_is_list($colors) || count($colors) > self::MAXIMUM_COLORS)) {
            throw new InvalidValueException('Map color list must be bounded.');
        }
        $this->colors = $colors;
        foreach ([$width, $height, $xOffset, $yOffset] as $value) {
            if ($value !== null && ($value < -0x80000000 || $value > 0x7fffffff)) {
                throw new InvalidValueException('Map texture geometry is outside its wire range.');
            }
        }
        foreach ($colors ?? [] as $color) {
            if ($color < -0x80000000 || $color > 0x7fffffff) {
                throw new InvalidValueException('Map color is outside its wire range.');
            }
        }
    }

    public function packetId(): int { return PacketIds::CLIENTBOUND_MAP_ITEM_DATA; }

    public function encode(): string
    {
        $writer = CodecSupport::writer()->writeSignedVarLong($this->uniqueMapId)
            ->writeUnsignedByte($this->dimensionId);
        $writer = CodecSupport::writeBoolean($writer, $this->locked)
            ->writeSignedVarInt($this->origin->x)->writeSignedVarInt($this->origin->y)->writeSignedVarInt($this->origin->z);
        $writer = self::writeOptionalList($writer, $this->trackedEntityIds, static fn (ByteBufferWriter $w, int $id): ByteBufferWriter => $w->writeSignedVarLong($id));
        $writer = self::writeOptionalScalar($writer, $this->scale, static fn (ByteBufferWriter $w, int $value): ByteBufferWriter => $w->writeUnsignedByte($value & 0xff));
        $writer = self::writeOptionalList($writer, $this->trackedObjects, self::writeTrackedObject(...));
        $writer = self::writeOptionalList($writer, $this->decorations, self::writeDecoration(...));
        foreach ([$this->width, $this->height, $this->xOffset, $this->yOffset] as $value) {
            $writer = self::writeOptionalScalar($writer, $value, static fn (ByteBufferWriter $w, int $v): ByteBufferWriter => $w->writeSignedVarInt($v));
        }
        return self::writeOptionalList(
            $writer,
            $this->colors,
            static fn (ByteBufferWriter $w, int $color): ByteBufferWriter => $w->writeSignedIntLE($color),
        )->toString();
    }

    public static function decode(string $bytes): self
    {
        $mapId = CodecSupport::reader($bytes)->readSignedVarLong();
        $dimension = $mapId->reader->readUnsignedByte();
        [$locked, $reader] = CodecSupport::readBoolean($dimension->reader);
        $x = $reader->readSignedVarInt();
        $y = $x->reader->readSignedVarInt();
        $z = $y->reader->readSignedVarInt();
        [$trackedEntityIds, $reader] = self::readOptionalList($z->reader, self::MAXIMUM_TRACKED_OBJECTS, static function (ByteBufferReader $r): array {
            $value = $r->readSignedVarLong();
            return [$value->value, $value->reader];
        });
        [$scale, $reader] = self::readOptionalScalar($reader, static function (ByteBufferReader $r): array {
            $value = $r->readUnsignedByte();
            return [$value->value > 0x7f ? $value->value - 0x100 : $value->value, $value->reader];
        });
        [$trackedObjects, $reader] = self::readOptionalList($reader, self::MAXIMUM_TRACKED_OBJECTS, self::readTrackedObject(...));
        [$decorations, $reader] = self::readOptionalList($reader, self::MAXIMUM_DECORATIONS, self::readDecoration(...));
        $geometry = [];
        for ($index = 0; $index < 4; ++$index) {
            [$geometry[], $reader] = self::readOptionalScalar($reader, static function (ByteBufferReader $r): array {
                $value = $r->readSignedVarInt();
                return [$value->value, $value->reader];
            });
        }
        [$colors, $reader] = self::readOptionalList($reader, self::MAXIMUM_COLORS, static function (ByteBufferReader $r): array {
            $value = $r->readSignedIntLE();
            return [$value->value, $value->reader];
        });
        CodecSupport::requireEnd($reader);
        return new self(
            $mapId->value, $dimension->value, $locked, new BlockPosition($x->value, $y->value, $z->value),
            $trackedEntityIds, $scale, $trackedObjects, $decorations,
            $geometry[0], $geometry[1], $geometry[2], $geometry[3], $colors,
        );
    }

    private static function writeTrackedObject(ByteBufferWriter $writer, MapTrackedObject $object): ByteBufferWriter
    {
        $writer = $writer->writeSignedIntLE($object->type->value);
        $writer = CodecSupport::writeBoolean($writer, $object->entityId !== null);
        if ($object->entityId !== null) {
            $writer = $writer->writeSignedVarLong($object->entityId);
        }
        $writer = CodecSupport::writeBoolean($writer, $object->position !== null);
        if ($object->position !== null) {
            $writer = $writer->writeSignedVarInt($object->position->x)
                ->writeSignedVarInt($object->position->y)->writeSignedVarInt($object->position->z);
        }
        return $writer;
    }

    /** @return array{MapTrackedObject, ByteBufferReader} */
    private static function readTrackedObject(ByteBufferReader $reader): array
    {
        $type = $reader->readSignedIntLE();
        $objectType = MapTrackedObjectType::tryFrom($type->value);
        if ($objectType === null) {
            throw new MalformedDataException('Map tracked-object type is unsupported.');
        }
        [$hasEntity, $reader] = CodecSupport::readBoolean($type->reader);
        $entityId = null;
        if ($hasEntity) {
            $value = $reader->readSignedVarLong();
            $entityId = $value->value;
            $reader = $value->reader;
        }
        [$hasPosition, $reader] = CodecSupport::readBoolean($reader);
        $position = null;
        if ($hasPosition) {
            $x = $reader->readSignedVarInt();
            $y = $x->reader->readSignedVarInt();
            $z = $y->reader->readSignedVarInt();
            $position = new BlockPosition($x->value, $y->value, $z->value);
            $reader = $z->reader;
        }
        if ($hasEntity === $hasPosition || $hasEntity !== ($objectType === MapTrackedObjectType::Entity)) {
            throw new MalformedDataException('Map tracked-object payload does not match its type.');
        }
        if ($hasEntity) {
            if ($entityId === null) {
                throw new MalformedDataException('Map tracked entity is missing its identity.');
            }
            return [MapTrackedObject::entity($entityId), $reader];
        }
        if ($position === null) {
            throw new MalformedDataException('Map tracked block is missing its position.');
        }
        return [MapTrackedObject::block($position), $reader];
    }

    private static function writeDecoration(ByteBufferWriter $writer, MapDecoration $decoration): ByteBufferWriter
    {
        return $writer->writeUnsignedByte($decoration->image & 0xff)->writeUnsignedByte($decoration->rotation)
            ->writeUnsignedByte($decoration->xOffset)->writeUnsignedByte($decoration->yOffset)
            ->writeString($decoration->label, CodecSupport::MAX_SHORT_STRING_BYTES)
            ->writeSignedIntLE($decoration->color);
    }

    /** @return array{MapDecoration, ByteBufferReader} */
    private static function readDecoration(ByteBufferReader $reader): array
    {
        $image = $reader->readUnsignedByte();
        $rotation = $image->reader->readUnsignedByte();
        $x = $rotation->reader->readUnsignedByte();
        $y = $x->reader->readUnsignedByte();
        $label = $y->reader->readString(CodecSupport::MAX_SHORT_STRING_BYTES);
        $color = $label->reader->readSignedIntLE();
        return [new MapDecoration(
            $image->value > 0x7f ? $image->value - 0x100 : $image->value,
            $rotation->value, $x->value, $y->value, $label->value, $color->value,
        ), $color->reader];
    }

    /**
     * @param callable(ByteBufferWriter, int): ByteBufferWriter $write
     */
    private static function writeOptionalScalar(ByteBufferWriter $writer, ?int $value, callable $write): ByteBufferWriter
    {
        $writer = CodecSupport::writeBoolean($writer, $value !== null);
        return $value === null ? $writer : $write($writer, $value);
    }

    /**
     * @param callable(ByteBufferReader): array{int, ByteBufferReader} $read
     * @return array{?int, ByteBufferReader}
     */
    private static function readOptionalScalar(ByteBufferReader $reader, callable $read): array
    {
        [$present, $reader] = CodecSupport::readBoolean($reader);
        return $present ? $read($reader) : [null, $reader];
    }

    /**
     * @template T
     * @param null|list<T> $values
     * @param callable(ByteBufferWriter, T): ByteBufferWriter $write
     */
    private static function writeOptionalList(ByteBufferWriter $writer, ?array $values, callable $write): ByteBufferWriter
    {
        $writer = CodecSupport::writeBoolean($writer, $values !== null);
        if ($values === null) {
            return $writer;
        }
        $writer = $writer->writeUnsignedVarInt(count($values));
        foreach ($values as $value) {
            $writer = $write($writer, $value);
        }
        return $writer;
    }

    /**
     * @template T
     * @param callable(ByteBufferReader): array{T, ByteBufferReader} $read
     * @return array{null|list<T>, ByteBufferReader}
     */
    private static function readOptionalList(ByteBufferReader $reader, int $maximum, callable $read): array
    {
        [$present, $reader] = CodecSupport::readBoolean($reader);
        if (!$present) {
            return [null, $reader];
        }
        $count = $reader->readUnsignedVarInt();
        if ($count->value > $maximum) {
            throw new MalformedDataException('Map data list exceeds its limit.');
        }
        $reader = $count->reader;
        $values = [];
        for ($index = 0; $index < $count->value; ++$index) {
            [$values[], $reader] = $read($reader);
        }
        return [$values, $reader];
    }

}
