<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

use Bedriox\Protocol\Exception\InvalidValueException;
use Bedriox\Protocol\Exception\MalformedDataException;
use Bedriox\Protocol\Value\UnsignedLong;

/** Bounded serverbound notification; it does not authorize the requested action. */
final readonly class PlayerActionPacket implements Packet
{
    public function __construct(
        public UnsignedLong $runtimeEntityId,
        public PlayerActionType $action,
        public BlockPosition $blockPosition,
        public BlockPosition $resultPosition,
        public int $face,
    ) {
        if ($face < -0x80000000 || $face > 0x7fffffff) {
            throw new InvalidValueException('Player action face must fit a signed 32-bit integer.');
        }
    }

    public function packetId(): int { return PacketIds::PLAYER_ACTION; }

    public function encode(): string
    {
        return CodecSupport::writer()->writeUnsignedVarLong($this->runtimeEntityId)
            ->writeSignedVarInt($this->action->value)
            ->writeSignedVarInt($this->blockPosition->x)->writeSignedVarInt($this->blockPosition->y)
            ->writeSignedVarInt($this->blockPosition->z)
            ->writeSignedVarInt($this->resultPosition->x)->writeSignedVarInt($this->resultPosition->y)
            ->writeSignedVarInt($this->resultPosition->z)->writeSignedVarInt($this->face)->toString();
    }

    public static function decode(string $bytes): self
    {
        $runtime = CodecSupport::reader($bytes)->readUnsignedVarLong();
        $action = $runtime->reader->readSignedVarInt();
        $actionType = PlayerActionType::tryFrom($action->value);
        if ($actionType === null) {
            throw new MalformedDataException('Player action type is unknown.');
        }
        $values = [];
        $reader = $action->reader;
        for ($index = 0; $index < 7; ++$index) {
            $value = $reader->readSignedVarInt();
            $values[] = $value->value;
            $reader = $value->reader;
        }
        CodecSupport::requireEnd($reader);
        return new self(
            $runtime->value,
            $actionType,
            new BlockPosition($values[0], $values[1], $values[2]),
            new BlockPosition($values[3], $values[4], $values[5]),
            $values[6],
        );
    }
}
