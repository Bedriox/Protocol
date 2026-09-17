<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

use Bedriox\Protocol\Exception\InvalidValueException;

final readonly class SetPlayerGameTypePacket implements Packet
{
    public function __construct(public int $gameType = 0)
    {
        if ($gameType < 0 || $gameType > 6) { throw new InvalidValueException('Game type is outside the protocol range.'); }
    }
    public function packetId(): int { return PacketIds::SET_PLAYER_GAME_TYPE; }
    public function encode(): string { return CodecSupport::writer()->writeSignedVarInt($this->gameType)->toString(); }
}
