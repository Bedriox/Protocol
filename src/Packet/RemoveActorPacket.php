<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

/** Removes one actor identified by its authoritative unique entity ID. */
final readonly class RemoveActorPacket implements Packet
{
    public function __construct(public int $actorUniqueId) {}

    public function packetId(): int
    {
        return PacketIds::REMOVE_ACTOR;
    }

    public function encode(): string
    {
        return CodecSupport::writer()->writeSignedVarLong($this->actorUniqueId)->toString();
    }

    public static function decode(string $bytes): self
    {
        $actorUniqueId = CodecSupport::reader($bytes)->readSignedVarLong();
        CodecSupport::requireEnd($actorUniqueId->reader);

        return new self($actorUniqueId->value);
    }
}
