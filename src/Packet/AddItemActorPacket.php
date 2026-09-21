<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

use Bedriox\Protocol\Exception\InvalidValueException;
use Bedriox\Protocol\Value\UnsignedLong;

/** Spawns one dropped-item actor with authoritative stack, movement, and metadata. */
final readonly class AddItemActorPacket implements Packet
{
    /** @param list<ActorMetadata> $metadata */
    public function __construct(
        public int $actorUniqueId,
        public UnsignedLong $runtimeEntityId,
        public InventoryItemStack $item,
        public float $x,
        public float $y,
        public float $z,
        public float $motionX,
        public float $motionY,
        public float $motionZ,
        public array $metadata = [],
        public bool $fromFishing = false,
    ) {
        if ($item->runtimeId === 0 || $item->count < 1) {
            throw new InvalidValueException('Dropped-item actors require a non-empty item stack.');
        }
        foreach ([$x, $y, $z, $motionX, $motionY, $motionZ] as $value) {
            CodecSupport::validateFiniteFloat($value, 'Dropped-item actor vector');
        }
        ActorMetadataCollection::validate($metadata);
    }

    public function packetId(): int { return PacketIds::ADD_ITEM_ACTOR; }

    public function encode(): string
    {
        $writer = CodecSupport::writer()->writeSignedVarLong($this->actorUniqueId)
            ->writeUnsignedVarLong($this->runtimeEntityId);
        $writer = InventoryItemStackWireCodec::write($writer, $this->item)
            ->writeFloatLE($this->x)->writeFloatLE($this->y)->writeFloatLE($this->z)
            ->writeFloatLE($this->motionX)->writeFloatLE($this->motionY)->writeFloatLE($this->motionZ);
        return CodecSupport::writeBoolean(
            ActorMetadataCollection::write($writer, $this->metadata),
            $this->fromFishing,
        )->toString();
    }

    public static function decode(string $bytes): self
    {
        $uniqueId = CodecSupport::reader($bytes)->readSignedVarLong();
        $runtimeId = $uniqueId->reader->readUnsignedVarLong();
        [$item, $reader] = InventoryItemStackWireCodec::read($runtimeId->reader);
        $vectors = [];
        for ($index = 0; $index < 6; ++$index) {
            $value = $reader->readFloatLE();
            CodecSupport::validateFiniteFloat($value->value, 'Dropped-item actor vector', true);
            $vectors[] = $value->value;
            $reader = $value->reader;
        }
        [$metadata, $reader] = ActorMetadataCollection::read($reader);
        [$fromFishing, $reader] = CodecSupport::readBoolean($reader);
        CodecSupport::requireEnd($reader);
        try {
            return new self(
                $uniqueId->value,
                $runtimeId->value,
                $item,
                $vectors[0],
                $vectors[1],
                $vectors[2],
                $vectors[3],
                $vectors[4],
                $vectors[5],
                $metadata,
                $fromFishing,
            );
        } catch (InvalidValueException $e) {
            throw new \Bedriox\Protocol\Exception\MalformedDataException('Dropped-item actor is invalid.', previous: $e);
        }
    }
}
