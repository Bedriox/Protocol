<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

use Bedriox\Protocol\Exception\MalformedDataException;
use Bedriox\Protocol\Value\UnsignedLong;

final readonly class UpdatePlayerGameTypePacket implements Packet
{
    public function __construct(
        public GameType $gameType,
        public int $actorUniqueId,
        public UnsignedLong $tick,
    ) {}

    public function packetId(): int { return PacketIds::UPDATE_PLAYER_GAME_TYPE; }

    public function encode(): string
    {
        return CodecSupport::writer()->writeSignedVarInt($this->gameType->value)
            ->writeSignedVarLong($this->actorUniqueId)
            ->writeUnsignedVarLong($this->tick)->toString();
    }

    public static function decode(string $bytes): self
    {
        $gameType = CodecSupport::reader($bytes)->readSignedVarInt();
        $typed = GameType::tryFrom($gameType->value);
        if ($typed === null) {
            throw new MalformedDataException('Updated player game type is unknown.');
        }
        $actorUniqueId = $gameType->reader->readSignedVarLong();
        $tick = $actorUniqueId->reader->readUnsignedVarLong();
        CodecSupport::requireEnd($tick->reader);
        return new self($typed, $actorUniqueId->value, $tick->value);
    }
}
