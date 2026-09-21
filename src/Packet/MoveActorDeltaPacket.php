<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

use Bedriox\Protocol\Exception\MalformedDataException;
use Bedriox\Protocol\Value\UnsignedLong;

/** Protocol-2193 sparse actor movement update with an authoritative tick. */
final readonly class MoveActorDeltaPacket implements Packet
{
    private const float DEGREES_PER_BYTE = 360.0 / 256.0;

    public function __construct(
        public UnsignedLong $runtimeEntityId,
        public ?float $x,
        public ?float $y,
        public ?float $z,
        public ?float $pitch,
        public ?float $yaw,
        public ?float $headYaw,
        public bool $onGround,
        public bool $forceMove,
        public bool $forceMoveLocalEntity,
        public bool $forceCompletion,
        public UnsignedLong $tick,
    ) {
        foreach (['x' => $x, 'y' => $y, 'z' => $z, 'pitch' => $pitch, 'yaw' => $yaw, 'head yaw' => $headYaw] as $field => $value) {
            if ($value !== null) {
                CodecSupport::validateFiniteFloat($value, "Delta-move {$field}");
            }
        }
    }

    public function packetId(): int { return PacketIds::MOVE_ACTOR_DELTA; }

    public function encode(): string
    {
        $writer = CodecSupport::writer()->writeUnsignedVarLong($this->runtimeEntityId);
        foreach ([$this->x, $this->y, $this->z] as $value) {
            $writer = CodecSupport::writeBoolean($writer, $value !== null);
            if ($value !== null) {
                $writer = $writer->writeFloatLE($value);
            }
        }
        foreach ([$this->pitch, $this->yaw, $this->headYaw] as $value) {
            $writer = CodecSupport::writeBoolean($writer, $value !== null);
            if ($value !== null) {
                $writer = $writer->writeUnsignedByte(self::angleToByte($value));
            }
        }
        foreach ([$this->onGround, $this->forceMove, $this->forceMoveLocalEntity, $this->forceCompletion] as $value) {
            $writer = CodecSupport::writeBoolean($writer, $value);
        }
        return $writer->writeUnsignedVarLong($this->tick)->toString();
    }

    public static function decode(string $bytes): self
    {
        $runtimeId = CodecSupport::reader($bytes)->readUnsignedVarLong();
        $reader = $runtimeId->reader;
        $position = [];
        for ($index = 0; $index < 3; ++$index) {
            [$present, $reader] = CodecSupport::readBoolean($reader);
            $value = null;
            if ($present) {
                $read = $reader->readFloatLE();
                CodecSupport::validateFiniteFloat($read->value, 'Delta-move position', true);
                $value = $read->value;
                $reader = $read->reader;
            }
            $position[] = $value;
        }
        $rotation = [];
        for ($index = 0; $index < 3; ++$index) {
            [$present, $reader] = CodecSupport::readBoolean($reader);
            $value = null;
            if ($present) {
                $read = $reader->readUnsignedByte();
                $value = $read->value * self::DEGREES_PER_BYTE;
                $reader = $read->reader;
            }
            $rotation[] = $value;
        }
        $flags = [];
        for ($index = 0; $index < 4; ++$index) {
            [$flags[], $reader] = CodecSupport::readBoolean($reader);
        }
        $tick = $reader->readUnsignedVarLong();
        CodecSupport::requireEnd($tick->reader);
        try {
            return new self(
                $runtimeId->value,
                $position[0],
                $position[1],
                $position[2],
                $rotation[0],
                $rotation[1],
                $rotation[2],
                $flags[0],
                $flags[1],
                $flags[2],
                $flags[3],
                $tick->value,
            );
        } catch (\Bedriox\Protocol\Exception\InvalidValueException $e) {
            throw new MalformedDataException('Delta-move packet is invalid.', previous: $e);
        }
    }

    private static function angleToByte(float $degrees): int
    {
        $scaled = (int) ($degrees / self::DEGREES_PER_BYTE);
        return (($scaled % 256) + 256) % 256;
    }
}
