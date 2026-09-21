<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

use Bedriox\Protocol\Exception\InvalidValueException;
use Bedriox\Protocol\Exception\MalformedDataException;

/** Current named level-sound event with actor and optional fire-position context. */
final readonly class LevelSoundEventPacket implements Packet
{
    public function __construct(
        public LevelSoundEventName $sound,
        public LevelEventPosition $position,
        public int $extraData = 0,
        public string $identifier = '',
        public bool $babySound = false,
        public bool $relativeVolumeDisabled = false,
        public int $actorUniqueId = 0,
        public ?LevelEventPosition $firePosition = null,
    ) {
        if ($extraData < -0x80000000 || $extraData > 0x7fffffff) {
            throw new InvalidValueException('Level sound extra data must fit a signed 32-bit integer.');
        }
        CodecSupport::validateString($identifier, CodecSupport::MAX_SHORT_STRING_BYTES, 'Level sound identifier');
    }

    public function packetId(): int { return PacketIds::LEVEL_SOUND_EVENT; }

    public function encode(): string
    {
        $writer = CodecSupport::writer()->writeString($this->sound->value, CodecSupport::MAX_SHORT_STRING_BYTES)
            ->writeFloatLE($this->position->x)->writeFloatLE($this->position->y)->writeFloatLE($this->position->z)
            ->writeSignedVarInt($this->extraData)
            ->writeString($this->identifier, CodecSupport::MAX_SHORT_STRING_BYTES);
        $writer = CodecSupport::writeBoolean($writer, $this->babySound);
        $writer = CodecSupport::writeBoolean($writer, $this->relativeVolumeDisabled)
            ->writeSignedLongLE($this->actorUniqueId);
        $writer = CodecSupport::writeBoolean($writer, $this->firePosition !== null);
        if ($this->firePosition !== null) {
            $writer = $writer->writeFloatLE($this->firePosition->x)
                ->writeFloatLE($this->firePosition->y)
                ->writeFloatLE($this->firePosition->z);
        }
        return $writer->toString();
    }

    public static function decode(string $bytes): self
    {
        $sound = CodecSupport::reader($bytes)->readString(CodecSupport::MAX_SHORT_STRING_BYTES);
        $reader = $sound->reader;
        $coordinates = [];
        for ($index = 0; $index < 3; ++$index) {
            $coordinate = $reader->readFloatLE();
            CodecSupport::validateFiniteFloat($coordinate->value, 'Level sound position', true);
            $coordinates[] = $coordinate->value;
            $reader = $coordinate->reader;
        }
        $extraData = $reader->readSignedVarInt();
        $identifier = $extraData->reader->readString(CodecSupport::MAX_SHORT_STRING_BYTES);
        [$babySound, $reader] = CodecSupport::readBoolean($identifier->reader);
        [$relativeVolumeDisabled, $reader] = CodecSupport::readBoolean($reader);
        $actorUniqueId = $reader->readSignedLongLE();
        [$hasFirePosition, $reader] = CodecSupport::readBoolean($actorUniqueId->reader);
        $firePosition = null;
        if ($hasFirePosition) {
            $fire = [];
            for ($index = 0; $index < 3; ++$index) {
                $coordinate = $reader->readFloatLE();
                CodecSupport::validateFiniteFloat($coordinate->value, 'Level sound fire position', true);
                $fire[] = $coordinate->value;
                $reader = $coordinate->reader;
            }
            $firePosition = new LevelEventPosition($fire[0], $fire[1], $fire[2]);
        }
        CodecSupport::requireEnd($reader);
        try {
            return new self(
                new LevelSoundEventName($sound->value),
                new LevelEventPosition($coordinates[0], $coordinates[1], $coordinates[2]),
                $extraData->value,
                $identifier->value,
                $babySound,
                $relativeVolumeDisabled,
                $actorUniqueId->value,
                $firePosition,
            );
        } catch (InvalidValueException $e) {
            throw new MalformedDataException('Level sound event is invalid.', previous: $e);
        }
    }
}
