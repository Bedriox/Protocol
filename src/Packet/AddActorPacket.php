<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

use Bedriox\Protocol\Exception\InvalidValueException;
use Bedriox\Protocol\Exception\MalformedDataException;
use Bedriox\Protocol\Value\UnsignedLong;

/** Complete protocol-2193 spawn snapshot for one non-player actor. */
final readonly class AddActorPacket implements Packet
{
    private const int MAXIMUM_IDENTIFIER_BYTES = 128;
    private const int MAXIMUM_ATTRIBUTES = 64;
    private const int MAXIMUM_LINKS = 64;

    /**
     * @param list<ActorSpawnAttribute> $attributes
     * @param list<ActorMetadata> $metadata
     * @param list<ActorLink> $links
     */
    public function __construct(
        public int $actorUniqueId,
        public UnsignedLong $runtimeEntityId,
        public string $identifier,
        public float $x,
        public float $y,
        public float $z,
        public float $motionX,
        public float $motionY,
        public float $motionZ,
        public float $pitch,
        public float $yaw,
        public float $headYaw,
        public float $bodyYaw,
        public array $attributes = [],
        public array $metadata = [],
        public ActorProperties $properties = new ActorProperties(),
        public array $links = [],
    ) {
        CodecSupport::validateString($identifier, self::MAXIMUM_IDENTIFIER_BYTES, 'Actor identifier');
        if ($identifier === '') {
            throw new InvalidValueException('Actor identifier cannot be empty.');
        }
        foreach ([$x, $y, $z, $motionX, $motionY, $motionZ, $pitch, $yaw, $headYaw, $bodyYaw] as $value) {
            CodecSupport::validateFiniteFloat($value, 'Add-actor vector or rotation');
        }
        CodecSupport::validateCount($attributes, self::MAXIMUM_ATTRIBUTES, 'Actor spawn attributes');
        foreach ($attributes as $attribute) {
            if (!$attribute instanceof ActorSpawnAttribute) {
                throw new InvalidValueException('Actor spawn attributes must contain typed values.');
            }
        }
        ActorMetadataCollection::validate($metadata);
        CodecSupport::validateCount($links, self::MAXIMUM_LINKS, 'Actor links');
        foreach ($links as $link) {
            if (!$link instanceof ActorLink) {
                throw new InvalidValueException('Actor links must contain typed values.');
            }
        }
    }

    public function packetId(): int
    {
        return PacketIds::ADD_ACTOR;
    }

    public function encode(): string
    {
        $writer = CodecSupport::writer()->writeSignedVarLong($this->actorUniqueId)
            ->writeUnsignedVarLong($this->runtimeEntityId)
            ->writeString($this->identifier, self::MAXIMUM_IDENTIFIER_BYTES);
        foreach ([$this->x, $this->y, $this->z, $this->motionX, $this->motionY, $this->motionZ,
            $this->pitch, $this->yaw, $this->headYaw, $this->bodyYaw] as $value) {
            $writer = $writer->writeFloatLE($value);
        }
        $writer = $writer->writeUnsignedVarInt(count($this->attributes));
        foreach ($this->attributes as $attribute) {
            $writer = $attribute->write($writer);
        }
        $writer = ActorMetadataCollection::write($writer, $this->metadata);
        $writer = $this->properties->write($writer)->writeUnsignedVarInt(count($this->links));
        foreach ($this->links as $link) {
            $writer = $link->write($writer);
        }
        return $writer->toString();
    }

    public static function decode(string $bytes): self
    {
        $uniqueId = CodecSupport::reader($bytes)->readSignedVarLong();
        $runtimeId = $uniqueId->reader->readUnsignedVarLong();
        $identifier = $runtimeId->reader->readString(self::MAXIMUM_IDENTIFIER_BYTES);
        $vectors = [];
        $reader = $identifier->reader;
        for ($index = 0; $index < 10; ++$index) {
            $value = $reader->readFloatLE();
            CodecSupport::validateFiniteFloat($value->value, 'Add-actor vector or rotation', true);
            $vectors[] = $value->value;
            $reader = $value->reader;
        }

        $attributeCount = $reader->readUnsignedVarInt();
        if ($attributeCount->value > self::MAXIMUM_ATTRIBUTES) {
            throw new MalformedDataException('Actor spawn attribute count exceeds its limit.');
        }
        $reader = $attributeCount->reader;
        $attributes = [];
        for ($index = 0; $index < $attributeCount->value; ++$index) {
            [$attributes[], $reader] = ActorSpawnAttribute::read($reader);
        }
        [$metadata, $reader] = ActorMetadataCollection::read($reader);
        [$properties, $reader] = ActorProperties::read($reader);
        $linkCount = $reader->readUnsignedVarInt();
        if ($linkCount->value > self::MAXIMUM_LINKS) {
            throw new MalformedDataException('Actor-link count exceeds its limit.');
        }
        $reader = $linkCount->reader;
        $links = [];
        for ($index = 0; $index < $linkCount->value; ++$index) {
            [$links[], $reader] = ActorLink::read($reader);
        }
        CodecSupport::requireEnd($reader);

        try {
            return new self(
                $uniqueId->value,
                $runtimeId->value,
                $identifier->value,
                $vectors[0],
                $vectors[1],
                $vectors[2],
                $vectors[3],
                $vectors[4],
                $vectors[5],
                $vectors[6],
                $vectors[7],
                $vectors[8],
                $vectors[9],
                $attributes,
                $metadata,
                $properties,
                $links,
            );
        } catch (InvalidValueException $e) {
            throw new MalformedDataException('Add-actor packet is invalid.', previous: $e);
        }
    }
}
