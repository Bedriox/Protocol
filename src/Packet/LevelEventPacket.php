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
