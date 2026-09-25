<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

use Bedriox\Protocol\Exception\InvalidValueException;
use Bedriox\Protocol\Exception\MalformedDataException;

/** Immutable block animation/state notification. */
final readonly class BlockEventPacket implements Packet
{
    public function __construct(
        public BlockPosition $position,
        public BlockEventType $eventType,
        public int $eventData,
    ) {
        if ($eventData < -0x80000000 || $eventData > 0x7fffffff) {
            throw new InvalidValueException('Block-event data must fit a signed 32-bit integer.');
        }
    }

    public static function containerState(BlockPosition $position, bool $open): self
    {
        return new self($position, BlockEventType::ChangeState, $open ? 1 : 0);
    }

    public function packetId(): int { return PacketIds::BLOCK_EVENT; }

    public function encode(): string
    {
        return CodecSupport::writer()->writeSignedVarInt($this->position->x)
            ->writeSignedVarInt($this->position->y)->writeSignedVarInt($this->position->z)
            ->writeSignedVarInt($this->eventType->value)->writeSignedVarInt($this->eventData)->toString();
    }

    public static function decode(string $bytes): self
    {
        $x = CodecSupport::reader($bytes)->readSignedVarInt();
        $y = $x->reader->readSignedVarInt();
        $z = $y->reader->readSignedVarInt();
        $type = $z->reader->readSignedVarInt();
        $eventType = BlockEventType::tryFrom($type->value)
            ?? throw new MalformedDataException('Block-event type is unknown.');
        $data = $type->reader->readSignedVarInt();
        CodecSupport::requireEnd($data->reader);
        try {
            return new self(new BlockPosition($x->value, $y->value, $z->value), $eventType, $data->value);
        } catch (InvalidValueException $e) {
            throw new MalformedDataException('Block event is invalid.', previous: $e);
        }
    }
}
