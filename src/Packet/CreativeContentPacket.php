<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

/** Empty Bedrock creative registry for the minimal server profile. */
final readonly class CreativeContentPacket implements Packet
{
    public function packetId(): int
    {
        return PacketIds::CREATIVE_CONTENT;
    }

    public function encode(): string
    {
        return CodecSupport::writer()->writeUnsignedVarInt(0)->writeUnsignedVarInt(0)->toString();
    }
}
