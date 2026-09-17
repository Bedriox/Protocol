<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

use Bedriox\Protocol\Exception\InvalidValueException;
use Bedriox\Protocol\Exception\MalformedDataException;
use Bedriox\Protocol\Value\UnsignedLong;

/** Clientbound server-authoritative movement rewind for one processed input tick. */
final readonly class CorrectPlayerMovePredictionPacket implements Packet
{
    public function __construct(
        public PredictionType $predictionType,
        public float $positionX,
        public float $positionY,
        public float $positionZ,
        public float $deltaX,
        public float $deltaY,
        public float $deltaZ,
        public float $rotationX,
        public float $rotationY,
        public ?float $vehicleAngularVelocity,
        public bool $onGround,
        public UnsignedLong $tick,
    ) {
        foreach ([
            'position X' => $positionX,
            'position Y' => $positionY,
            'position Z' => $positionZ,
            'delta X' => $deltaX,
            'delta Y' => $deltaY,
            'delta Z' => $deltaZ,
            'rotation X' => $rotationX,
            'rotation Y' => $rotationY,
        ] as $field => $value) {
            CodecSupport::validateFiniteFloat($value, 'Movement correction ' . $field);
        }
        if ($vehicleAngularVelocity !== null) {
            CodecSupport::validateFiniteFloat($vehicleAngularVelocity, 'Movement correction vehicle angular velocity');
        }
        if ($predictionType === PredictionType::Player && $vehicleAngularVelocity !== null) {
            throw new InvalidValueException('Player prediction cannot carry vehicle angular velocity.');
        }
    }

    public function packetId(): int { return PacketIds::CORRECT_PLAYER_MOVE_PREDICTION; }

    public function encode(): string
    {
        $writer = CodecSupport::writer()->writeUnsignedByte($this->predictionType->value);
        foreach ([
            $this->positionX, $this->positionY, $this->positionZ,
            $this->deltaX, $this->deltaY, $this->deltaZ,
            $this->rotationX, $this->rotationY,
        ] as $value) {
            $writer = $writer->writeFloatLE($value);
        }
        $writer = CodecSupport::writeBoolean($writer, $this->vehicleAngularVelocity !== null);
        if ($this->vehicleAngularVelocity !== null) {
            $writer = $writer->writeFloatLE($this->vehicleAngularVelocity);
        }
        return CodecSupport::writeBoolean($writer, $this->onGround)
            ->writeUnsignedVarLong($this->tick)->toString();
    }

    public static function decode(string $bytes): self
    {
        $typeValue = CodecSupport::reader($bytes)->readUnsignedByte();
        $predictionType = PredictionType::tryFrom($typeValue->value)
            ?? throw new MalformedDataException('Movement-correction prediction type is unknown.');
        $values = [];
        $reader = $typeValue->reader;
        foreach (['position', 'position', 'position', 'delta', 'delta', 'delta', 'rotation', 'rotation'] as $field) {
            $value = $reader->readFloatLE();
            CodecSupport::validateFiniteFloat($value->value, 'Movement correction ' . $field, true);
            $values[] = $value->value;
            $reader = $value->reader;
        }
        [$hasAngularVelocity, $reader] = CodecSupport::readBoolean($reader);
        $vehicleAngularVelocity = null;
        if ($hasAngularVelocity) {
            if ($predictionType !== PredictionType::Vehicle) {
                throw new MalformedDataException('Player prediction cannot carry vehicle angular velocity.');
            }
            $angularVelocity = $reader->readFloatLE();
            CodecSupport::validateFiniteFloat($angularVelocity->value, 'Movement correction vehicle angular velocity', true);
            $vehicleAngularVelocity = $angularVelocity->value;
            $reader = $angularVelocity->reader;
        }
        [$onGround, $reader] = CodecSupport::readBoolean($reader);
        $tick = $reader->readUnsignedVarLong();
        CodecSupport::requireEnd($tick->reader);
        return new self(
            $predictionType,
            $values[0], $values[1], $values[2],
            $values[3], $values[4], $values[5],
            $values[6], $values[7],
            $vehicleAngularVelocity,
            $onGround,
            $tick->value,
        );
    }
}
