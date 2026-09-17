<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

use Bedriox\Protocol\Exception\InvalidValueException;

final readonly class SetDifficultyPacket implements Packet
{
    public function __construct(public int $difficulty = 0)
    {
        if ($difficulty < 0 || $difficulty > 3) { throw new InvalidValueException('Difficulty is outside the protocol range.'); }
    }
    public function packetId(): int { return PacketIds::SET_DIFFICULTY; }
    public function encode(): string { return CodecSupport::writer()->writeUnsignedVarInt($this->difficulty)->toString(); }
}
