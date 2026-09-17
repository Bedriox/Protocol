<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

use Bedriox\Protocol\Exception\InvalidValueException;
use Bedriox\Protocol\Exception\MalformedDataException;
use Bedriox\Protocol\Value\UnsignedLong;

final readonly class MovePlayerPacket implements Packet
{
    public function __construct(
        public UnsignedLong $runtimeEntityId,
        public float $x,
        public float $y,
        public float $z,
        public float $pitch,
        public float $yaw,
        public float $headYaw,
        public int $mode,
        public bool $onGround,
        public UnsignedLong $ridingRuntimeEntityId,
        public UnsignedLong $tick,
        public int $teleportationCause = 0,
        public int $teleportEntityType = 0,
    ) {
        foreach (['x' => $x, 'y' => $y, 'z' => $z, 'pitch' => $pitch, 'yaw' => $yaw, 'headYaw' => $headYaw] as $field => $value) {
            CodecSupport::validateFiniteFloat($value, $field);
        }
        if ($mode < MovePlayerMode::NORMAL || $mode > MovePlayerMode::HEAD_ROTATION) {
            throw new InvalidValueException('Move-player mode is not defined by the supported Bedrock protocol.');
        }
        if ($mode === MovePlayerMode::TELEPORT) {
            if ($teleportationCause < 0 || $teleportationCause > 4) {
                throw new InvalidValueException('Teleportation cause is not defined by the supported Bedrock protocol.');
            }
        } elseif ($teleportationCause !== 0 || $teleportEntityType !== 0) {
            throw new InvalidValueException('Teleport metadata is only valid in teleport mode.');
        }
        if ($teleportEntityType < -0x80000000 || $teleportEntityType > 0x7fffffff) {
            throw new InvalidValueException('Teleport entity type must fit in a signed 32-bit integer.');
        }
    }

    public function packetId(): int { return PacketIds::MOVE_PLAYER; }

    public function encode(): string
    {
        $writer = CodecSupport::writer()->writeUnsignedVarLong($this->runtimeEntityId);
        foreach ([$this->x, $this->y, $this->z, $this->pitch, $this->yaw, $this->headYaw] as $value) {
            $writer = $writer->writeFloatLE($value);
        }
        $writer = $writer->writeUnsignedByte($this->mode);
        $writer = CodecSupport::writeBoolean($writer, $this->onGround)
            ->writeUnsignedVarLong($this->ridingRuntimeEntityId);
        $writer = CodecSupport::writeBoolean($writer, $this->mode === MovePlayerMode::TELEPORT);
        if ($this->mode === MovePlayerMode::TELEPORT) {
            $writer = $writer->writeSignedIntLE($this->teleportationCause)->writeSignedIntLE($this->teleportEntityType);
        }
        return $writer->writeUnsignedVarLong($this->tick)->toString();
    }

    public static function decode(string $bytes): self
    {
        $runtime = CodecSupport::reader($bytes)->readUnsignedVarLong();
        $values = [];
        $reader = $runtime->reader;
        foreach (['x', 'y', 'z', 'pitch', 'yaw', 'headYaw'] as $field) {
            $read = $reader->readFloatLE();
            CodecSupport::validateFiniteFloat($read->value, $field, true);
            $values[] = $read->value;
            $reader = $read->reader;
        }
        $mode = $reader->readUnsignedByte();
        if ($mode->value > MovePlayerMode::HEAD_ROTATION) {
            throw new MalformedDataException('Move-player mode is not defined by the supported Bedrock protocol.');
        }
        [$onGround, $reader] = CodecSupport::readBoolean($mode->reader);
        $riding = $reader->readUnsignedVarLong();
        $reader = $riding->reader;
        $cause = 0;
        $entityType = 0;
        [$hasTeleportData, $reader] = CodecSupport::readBoolean($reader);
        if ($hasTeleportData !== ($mode->value === MovePlayerMode::TELEPORT)) {
            throw new MalformedDataException('Move-player teleport-data presence does not match its mode.');
        }
        if ($hasTeleportData) {
            $causeRead = $reader->readSignedIntLE();
            if ($causeRead->value < 0 || $causeRead->value > 4) {
                throw new MalformedDataException('Teleportation cause is not defined by the supported Bedrock protocol.');
            }
            $entityRead = $causeRead->reader->readSignedIntLE();
            $cause = $causeRead->value;
            $entityType = $entityRead->value;
            $reader = $entityRead->reader;
        }
        $tick = $reader->readUnsignedVarLong();
        CodecSupport::requireEnd($tick->reader);
        return new self(
            $runtime->value,
            $values[0], $values[1], $values[2],
            $values[3], $values[4], $values[5],
            $mode->value,
            $onGround,
            $riding->value,
            $tick->value,
            $cause,
            $entityType,
        );
    }
}
