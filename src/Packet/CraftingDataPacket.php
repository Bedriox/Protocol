<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

/** Empty, clearing recipe registry for the no-items MVP. */
final readonly class CraftingDataPacket implements Packet
{
    public function packetId(): int { return PacketIds::CRAFTING_DATA; }
    public function encode(): string { return str_repeat("\0", 11) . "\1"; }
}
