<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

use Bedriox\Protocol\Exception\InvalidValueException;
use Bedriox\Protocol\Exception\MalformedDataException;

final readonly class SetDisplayObjectivePacket implements Packet
{
    public function __construct(
        public ScoreboardDisplaySlot $displaySlot,
        public string $objectiveId,
        public string $displayName,
        public string $criteria = 'dummy',
        public ScoreboardSortOrder $sortOrder = ScoreboardSortOrder::DESCENDING,
    ) {
        foreach ([
            'objective ID' => $this->objectiveId,
            'display name' => $this->displayName,
            'criteria' => $this->criteria,
        ] as $field => $value) {
            CodecSupport::validateString($value, CodecSupport::MAX_SHORT_STRING_BYTES, 'Scoreboard ' . $field);
        }
        if ($this->objectiveId === '' || $this->criteria === '') {
            throw new InvalidValueException('Scoreboard objective ID and criteria cannot be empty.');
        }
    }

    public function packetId(): int { return PacketIds::SET_DISPLAY_OBJECTIVE; }

    public function encode(): string
    {
        return CodecSupport::writer()
            ->writeString($this->displaySlot->value, CodecSupport::MAX_SHORT_STRING_BYTES)
            ->writeString($this->objectiveId, CodecSupport::MAX_SHORT_STRING_BYTES)
            ->writeString($this->displayName, CodecSupport::MAX_SHORT_STRING_BYTES)
            ->writeString($this->criteria, CodecSupport::MAX_SHORT_STRING_BYTES)
            ->writeSignedVarInt($this->sortOrder->value)
            ->toString();
    }

    public static function decode(string $bytes): self
    {
        $slotWire = CodecSupport::reader($bytes)->readString(CodecSupport::MAX_SHORT_STRING_BYTES);
        $slot = ScoreboardDisplaySlot::tryFrom($slotWire->value);
        if ($slot === null) {
            throw new MalformedDataException('Unknown scoreboard display slot.');
        }
        $objectiveId = $slotWire->reader->readString(CodecSupport::MAX_SHORT_STRING_BYTES);
        $displayName = $objectiveId->reader->readString(CodecSupport::MAX_SHORT_STRING_BYTES);
        $criteria = $displayName->reader->readString(CodecSupport::MAX_SHORT_STRING_BYTES);
        $sortWire = $criteria->reader->readSignedVarInt();
        $sortOrder = ScoreboardSortOrder::tryFrom($sortWire->value);
        if ($sortOrder === null) {
            throw new MalformedDataException('Unknown scoreboard sort order.');
        }
        CodecSupport::requireEnd($sortWire->reader);

        try {
            return new self($slot, $objectiveId->value, $displayName->value, $criteria->value, $sortOrder);
        } catch (InvalidValueException $exception) {
            throw new MalformedDataException($exception->getMessage(), previous: $exception);
        }
    }
}
