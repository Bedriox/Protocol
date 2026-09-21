<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

use Bedriox\Protocol\Exception\InvalidValueException;

/** Clientbound level event with opaque event-specific data. */
final readonly class LevelEventPacket implements Packet
{
    public function __construct(
        public int $eventId,
        public LevelEventPosition $position,
        public int $data,
    ) {
        foreach ([$eventId, $data] as $value) {
            if ($value < -0x80000000 || $value > 0x7fffffff) {
                throw new InvalidValueException('Level-event identifiers and data must fit signed 32-bit integers.');
            }
        }
    }

    public function packetId(): int { return PacketIds::LEVEL_EVENT; }

    public function type(): ?LevelEventType
    {
        return LevelEventType::tryFrom($this->eventId);
    }

    public static function startBlockBreak(LevelEventPosition $position, int $progress): self
    {
        return new self(LevelEventType::StartBlockBreak->value, $position, $progress);
    }

    public static function stopBlockBreak(LevelEventPosition $position): self
    {
        return new self(LevelEventType::StopBlockBreak->value, $position, 0);
    }

    public static function updateBlockBreak(LevelEventPosition $position, int $progress): self
    {
        return new self(LevelEventType::UpdateBlockBreak->value, $position, $progress);
    }

    public static function destroyBlock(LevelEventPosition $position, int $blockRuntimeId, bool $sound = true): self
    {
        return new self(
            ($sound ? LevelEventType::DestroyBlock : LevelEventType::DestroyBlockWithoutSound)->value,
            $position,
            $blockRuntimeId,
        );
    }

    public static function crackBlock(LevelEventPosition $position, int $blockRuntimeId): self
    {
        return new self(LevelEventType::CrackBlock->value, $position, $blockRuntimeId);
    }

    public static function punchBlock(LevelEventPosition $position, int $blockRuntimeId, int $face): self
    {
        $event = match ($face) {
            0 => LevelEventType::PunchBlockDown,
            1 => LevelEventType::PunchBlockUp,
            2 => LevelEventType::PunchBlockNorth,
            3 => LevelEventType::PunchBlockSouth,
            4 => LevelEventType::PunchBlockWest,
            5 => LevelEventType::PunchBlockEast,
            default => throw new InvalidValueException('Block face is outside the protocol range.'),
        };
        return new self($event->value, $position, $blockRuntimeId);
    }

    public function encode(): string
    {
        return CodecSupport::writer()->writeSignedVarInt($this->eventId)
            ->writeFloatLE($this->position->x)->writeFloatLE($this->position->y)->writeFloatLE($this->position->z)
            ->writeSignedVarInt($this->data)->toString();
    }

    public static function decode(string $bytes): self
    {
        $eventId = CodecSupport::reader($bytes)->readSignedVarInt();
        $x = $eventId->reader->readFloatLE();
        $y = $x->reader->readFloatLE();
        $z = $y->reader->readFloatLE();
        foreach ([$x->value, $y->value, $z->value] as $coordinate) {
            CodecSupport::validateFiniteFloat($coordinate, 'Level-event position', true);
        }
        $data = $z->reader->readSignedVarInt();
        CodecSupport::requireEnd($data->reader);
        return new self($eventId->value, new LevelEventPosition($x->value, $y->value, $z->value), $data->value);
    }
}
