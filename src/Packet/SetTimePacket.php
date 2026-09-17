<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

final readonly class SetTimePacket implements Packet
{
    public function __construct(public int $time = 0) {}
    public function packetId(): int { return PacketIds::SET_TIME; }
    public function encode(): string { return CodecSupport::writer()->writeSignedVarInt($this->time)->toString(); }
}
