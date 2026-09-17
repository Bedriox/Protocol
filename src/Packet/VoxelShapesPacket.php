<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

/** Bedrock empty voxel-shape registry for worlds without custom shapes. */
final readonly class VoxelShapesPacket implements Packet
{
    public function packetId(): int
    {
        return PacketIds::VOXEL_SHAPES;
    }

    public function encode(): string
    {
        return CodecSupport::writer()
            ->writeUnsignedVarInt(0)
            ->writeUnsignedVarInt(0)
            ->writeUnsignedShortLE(0)
            ->toString();
    }
}
