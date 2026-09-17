<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

final readonly class TrimDataPacket implements Packet
{
    public function packetId(): int { return PacketIds::TRIM_DATA; }
    public function encode(): string { return "\0\0"; }
}
