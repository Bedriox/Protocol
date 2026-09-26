<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

use Bedriox\Protocol\Value\UnsignedLong;

/** Bounded actor metadata and dynamic-property snapshot. */
final readonly class SetActorDataPacket implements Packet
{
    /** @param list<ActorMetadata> $metadata */
    public function __construct(
        public UnsignedLong $runtimeEntityId,
        public UnsignedLong $tick,
        public array $metadata = [],
        public ActorProperties $properties = new ActorProperties(),
    ) {
        ActorMetadataCollection::validate($metadata);
    }

    public static function baselinePlayer(
        UnsignedLong $runtimeEntityId,
        UnsignedLong $tick,
        string $name,
        bool $sneaking = false,
        bool $sprinting = false,
    ): self {
        return new self($runtimeEntityId, $tick, PlayerActorMetadata::baseline($name, $sneaking, $sprinting));
    }

    /** Emits the complete current player flag word so posture cannot clear baseline physics flags. */
    public static function playerPosture(
        UnsignedLong $runtimeEntityId,
        UnsignedLong $tick,
        bool $sneaking,
        bool $sprinting,
        bool $usingItem = false,
    ): self {
        return new self($runtimeEntityId, $tick, [
            ActorMetadata::long(0, PlayerActorMetadata::flags($sneaking, $sprinting, $usingItem)),
        ]);
    }

    public function packetId(): int { return PacketIds::SET_ACTOR_DATA; }

    public function encode(): string
    {
        $writer = CodecSupport::writer()->writeUnsignedVarLong($this->runtimeEntityId);
        $writer = ActorMetadataCollection::write($writer, $this->metadata);
        return $this->properties->write($writer)->writeUnsignedVarLong($this->tick)->toString();
    }

    public static function decode(string $bytes): self
    {
        $runtimeEntityId = CodecSupport::reader($bytes)->readUnsignedVarLong();
        [$metadata, $reader] = ActorMetadataCollection::read($runtimeEntityId->reader);
        [$properties, $reader] = ActorProperties::read($reader);
        $tick = $reader->readUnsignedVarLong();
        CodecSupport::requireEnd($tick->reader);
        return new self($runtimeEntityId->value, $tick->value, $metadata, $properties);
    }
}
