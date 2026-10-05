<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

use Bedriox\Protocol\Exception\InvalidValueException;
use Bedriox\Protocol\Exception\MalformedDataException;

/** Fixed-schema bidirectional boss-event state for protocol 2193. */
final readonly class BossEventPacket implements Packet
{
    public function __construct(
        public int $bossUniqueEntityId,
        public BossEventAction $action,
        public string $title = '',
        public string $filteredTitle = '',
        public float $healthPercentage = 0.0,
        public BossEventColor $color = BossEventColor::PINK,
        public BossEventOverlay $overlay = BossEventOverlay::PROGRESS,
    ) {
        CodecSupport::validateString($this->title, CodecSupport::MAX_SHORT_STRING_BYTES, 'Boss-event title');
        CodecSupport::validateString($this->filteredTitle, CodecSupport::MAX_SHORT_STRING_BYTES, 'Boss-event filtered title');
        CodecSupport::validateFiniteFloat($this->healthPercentage, 'Boss-event health percentage');
        if ($this->healthPercentage < 0.0 || $this->healthPercentage > 1.0) {
            throw new InvalidValueException('Boss-event health percentage must be between 0.0 and 1.0.');
        }
    }

    public function packetId(): int
    {
        return PacketIds::BOSS_EVENT;
    }

    public function encode(): string
    {
        return CodecSupport::writer()
            ->writeSignedVarLong($this->bossUniqueEntityId)
            ->writeUnsignedByte($this->action->value)
            ->writeString($this->title, CodecSupport::MAX_SHORT_STRING_BYTES)
            ->writeString($this->filteredTitle, CodecSupport::MAX_SHORT_STRING_BYTES)
            ->writeFloatLE($this->healthPercentage)
            ->writeUnsignedByte($this->color->value)
            ->writeUnsignedByte($this->overlay->value)
            ->toString();
    }

    public static function decode(string $bytes): self
    {
        $bossUniqueEntityId = CodecSupport::reader($bytes)->readSignedVarLong();
        $actionWire = $bossUniqueEntityId->reader->readUnsignedByte();
        $action = BossEventAction::tryFrom($actionWire->value);
        if ($action === null) {
            throw new MalformedDataException('Boss-event action is invalid for the current protocol.');
        }
        $title = $actionWire->reader->readString(CodecSupport::MAX_SHORT_STRING_BYTES);
        $filteredTitle = $title->reader->readString(CodecSupport::MAX_SHORT_STRING_BYTES);
        $healthPercentage = $filteredTitle->reader->readFloatLE();
        CodecSupport::validateFiniteFloat($healthPercentage->value, 'Boss-event health percentage', true);
        if ($healthPercentage->value < 0.0 || $healthPercentage->value > 1.0) {
            throw new MalformedDataException('Boss-event health percentage must be between 0.0 and 1.0.');
        }
        $colorWire = $healthPercentage->reader->readUnsignedByte();
        $color = BossEventColor::tryFrom($colorWire->value);
        if ($color === null) {
            throw new MalformedDataException('Boss-event color is invalid for the current protocol.');
        }
        $overlayWire = $colorWire->reader->readUnsignedByte();
        $overlay = BossEventOverlay::tryFrom($overlayWire->value);
        if ($overlay === null) {
            throw new MalformedDataException('Boss-event overlay is invalid for the current protocol.');
        }
        CodecSupport::requireEnd($overlayWire->reader);

        return new self(
            $bossUniqueEntityId->value,
            $action,
            $title->value,
            $filteredTitle->value,
            $healthPercentage->value,
            $color,
            $overlay,
        );
    }
}
