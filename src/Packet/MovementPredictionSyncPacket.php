<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

use Bedriox\Protocol\Codec\ByteBufferReader;
use Bedriox\Protocol\Codec\ByteBufferWriter;
use Bedriox\Protocol\Exception\InvalidValueException;
use Bedriox\Protocol\Exception\MalformedDataException;
use Bedriox\Protocol\Value\UnsignedLong;

/** Current-client movement properties reported for server-side reconciliation. */
final readonly class MovementPredictionSyncPacket implements Packet
{
    public const int ACTOR_FLAG_COUNT = 131;
    private const int MAX_FLAG_BYTES = 19;

    /** @var list<int> Sorted actor-flag indexes in the current protocol schema. */
    public array $actorFlags;

    /** @param array<array-key, mixed> $actorFlags */
    public function __construct(
        array $actorFlags,
        public float $boundingBoxX,
        public float $boundingBoxY,
        public float $boundingBoxZ,
        public float $speed,
        public float $underwaterSpeed,
        public float $lavaSpeed,
        public float $jumpStrength,
        public float $health,
        public float $hunger,
        public float $unknown1,
        public float $unknown2,
        public float $unknown3,
        public UnsignedLong $runtimeActorId,
        public bool $flying,
    ) {
        if (!array_is_list($actorFlags)) {
            throw new InvalidValueException('Movement-prediction actor flags must be a unique list.');
        }
        $validatedFlags = [];
        foreach ($actorFlags as $flag) {
            if (!is_int($flag)) {
                throw new InvalidValueException('Movement-prediction actor flags must be integer indexes.');
            }
            $validatedFlags[] = $flag;
        }
        if ($validatedFlags !== array_values(array_unique($validatedFlags))) {
            throw new InvalidValueException('Movement-prediction actor flags must be a unique list.');
        }
        $previous = -1;
        foreach ($validatedFlags as $flag) {
            if ($flag <= $previous || $flag >= self::ACTOR_FLAG_COUNT) {
                throw new InvalidValueException('Movement-prediction actor flags must be sorted current flag indexes.');
            }
            $previous = $flag;
        }
        $this->actorFlags = $validatedFlags;
        foreach ($this->floats() as $field => $value) {
            CodecSupport::validateFiniteFloat($value, 'Movement-prediction ' . $field);
        }
    }

    public function packetId(): int
    {
        return PacketIds::MOVEMENT_PREDICTION_SYNC;
    }

    public function encode(): string
    {
        $writer = self::writeActorFlags(CodecSupport::writer(), $this->actorFlags);
        foreach ($this->floats() as $value) {
            $writer = $writer->writeFloatLE($value);
        }
        return CodecSupport::writeBoolean($writer->writeUnsignedVarLong($this->runtimeActorId), $this->flying)
            ->toString();
    }

    public static function decode(string $bytes): self
    {
        [$actorFlags, $reader] = self::readActorFlags(CodecSupport::reader($bytes));
        $floats = [];
        for ($index = 0; $index < 12; ++$index) {
            $value = $reader->readFloatLE();
            CodecSupport::validateFiniteFloat($value->value, 'Movement-prediction float', true);
            $floats[] = $value->value;
            $reader = $value->reader;
        }
        $runtimeActorId = $reader->readUnsignedVarLong();
        [$flying, $reader] = CodecSupport::readBoolean($runtimeActorId->reader);
        CodecSupport::requireEnd($reader);
        try {
            return new self(
                $actorFlags,
                $floats[0], $floats[1], $floats[2],
                $floats[3], $floats[4], $floats[5],
                $floats[6], $floats[7], $floats[8],
                $floats[9], $floats[10], $floats[11],
                $runtimeActorId->value,
                $flying,
            );
        } catch (InvalidValueException $exception) {
            throw new MalformedDataException('Movement-prediction sync payload is invalid.', previous: $exception);
        }
    }

    /** @return array<string, float> */
    private function floats(): array
    {
        return [
            'bounding-box X' => $this->boundingBoxX,
            'bounding-box Y' => $this->boundingBoxY,
            'bounding-box Z' => $this->boundingBoxZ,
            'speed' => $this->speed,
            'underwater speed' => $this->underwaterSpeed,
            'lava speed' => $this->lavaSpeed,
            'jump strength' => $this->jumpStrength,
            'health' => $this->health,
            'hunger' => $this->hunger,
            'unknown field 1' => $this->unknown1,
            'unknown field 2' => $this->unknown2,
            'unknown field 3' => $this->unknown3,
        ];
    }

    /** @param list<int> $flags */
    private static function writeActorFlags(ByteBufferWriter $writer, array $flags): ByteBufferWriter
    {
        $highest = $flags === [] ? 0 : $flags[array_key_last($flags)];
        $byteCount = intdiv($highest, 7) + 1;
        $digits = array_fill(0, $byteCount, 0);
        foreach ($flags as $flag) {
            $digits[intdiv($flag, 7)] |= 1 << ($flag % 7);
        }
        foreach ($digits as $index => $digit) {
            $writer = $writer->writeUnsignedByte($digit | ($index + 1 < $byteCount ? 0x80 : 0));
        }
        return $writer;
    }

    /** @return array{list<int>, ByteBufferReader} */
    private static function readActorFlags(ByteBufferReader $reader): array
    {
        $flags = [];
        for ($byteIndex = 0; $byteIndex < self::MAX_FLAG_BYTES; ++$byteIndex) {
            $read = $reader->readUnsignedByte();
            $digit = $read->value & 0x7f;
            if ($byteIndex === self::MAX_FLAG_BYTES - 1 && $digit > 0x1f) {
                throw new MalformedDataException('Movement-prediction actor flags exceed the current schema.');
            }
            for ($bit = 0; $bit < 7; ++$bit) {
                $flag = ($byteIndex * 7) + $bit;
                if (($digit & (1 << $bit)) !== 0) {
                    if ($flag >= self::ACTOR_FLAG_COUNT) {
                        throw new MalformedDataException('Movement-prediction actor flag is outside the current schema.');
                    }
                    $flags[] = $flag;
                }
            }
            $reader = $read->reader;
            if (($read->value & 0x80) === 0) {
                if ($byteIndex > 0 && $digit === 0) {
                    throw new MalformedDataException('Movement-prediction actor flags are not canonically encoded.');
                }
                return [$flags, $reader];
            }
        }
        throw new MalformedDataException('Movement-prediction actor flags exceed their byte limit.');
    }
}
