<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

final readonly class SetSpawnPositionPacket implements Packet
{
    public function __construct(
        public int $spawnType = 0,
        public int $x = 0,
        public int $y = 64,
        public int $z = 0,
        public int $dimension = 0,
        public int $worldX = 0,
        public int $worldY = 64,
        public int $worldZ = 0,
    ) {}

    public function packetId(): int { return PacketIds::SET_SPAWN_POSITION; }
    public function encode(): string
    {
        return CodecSupport::writer()->writeSignedVarInt($this->spawnType)
            ->writeSignedVarInt($this->x)->writeSignedVarInt($this->y)->writeSignedVarInt($this->z)
            ->writeSignedVarInt($this->dimension)
            ->writeSignedVarInt($this->worldX)->writeSignedVarInt($this->worldY)->writeSignedVarInt($this->worldZ)->toString();
    }
}
