<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

use Bedriox\Protocol\Exception\InvalidValueException;
use Bedriox\Protocol\Exception\MalformedDataException;

final readonly class SetPlayerGameTypePacket implements Packet
{
    public GameType $gameType;

    public function __construct(GameType|int $gameType = GameType::Survival)
    {
        $typed = is_int($gameType) ? GameType::tryFrom($gameType) : $gameType;
        if ($typed === null) {
            throw new InvalidValueException('Game type is outside the protocol range.');
        }
        $this->gameType = $typed;
    }
    public function packetId(): int { return PacketIds::SET_PLAYER_GAME_TYPE; }
    public function encode(): string { return CodecSupport::writer()->writeSignedVarInt($this->gameType->value)->toString(); }

    public static function decode(string $bytes): self
    {
        $gameType = CodecSupport::reader($bytes)->readSignedVarInt();
        $typed = GameType::tryFrom($gameType->value);
        if ($typed === null) {
            throw new MalformedDataException('Game type is unknown.');
        }
        CodecSupport::requireEnd($gameType->reader);
        return new self($typed);
    }
}
