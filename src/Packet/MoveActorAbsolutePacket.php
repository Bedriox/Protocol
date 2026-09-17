<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

use Bedriox\Protocol\Exception\InvalidValueException;
use Bedriox\Protocol\Exception\MalformedDataException;
use Bedriox\Protocol\Value\UnsignedLong;

/** Absolute movement of one actor as observed by peer clients. */
final readonly class MoveActorAbsolutePacket implements Packet
{
    private const float DEGREES_PER_BYTE = 360.0 / 256.0;
    private const int KNOWN_FLAG_MASK = 0x0f;

    /** @param list<MoveActorAbsoluteFlag> $flags */
    public function __construct(
        public UnsignedLong $runtimeEntityId,
        public float $x,
        public float $y,
        public float $z,
        public float $pitch,
        public float $yaw,
        public float $headYaw,
        public array $flags = [],
    ) {
        foreach (['x' => $x, 'y' => $y, 'z' => $z, 'pitch' => $pitch, 'yaw' => $yaw, 'head yaw' => $headYaw] as $field => $value) {
            CodecSupport::validateFiniteFloat($value, "Move-actor {$field}");
        }

        $seen = 0;
        foreach ($flags as $flag) {
            if (!$flag instanceof MoveActorAbsoluteFlag || ($seen & $flag->value) !== 0) {
                throw new InvalidValueException('Move-actor flags must be unique typed values.');
            }
            $seen |= $flag->value;
        }
    }

    public function packetId(): int
    {
        return PacketIds::MOVE_ACTOR_ABSOLUTE;
    }

    public function onGround(): bool
    {
        return $this->hasFlag(MoveActorAbsoluteFlag::OnGround);
    }

    public function hasFlag(MoveActorAbsoluteFlag $expected): bool
    {
        foreach ($this->flags as $flag) {
            if ($flag === $expected) {
                return true;
            }
        }
        return false;
    }

    public function encode(): string
    {
        $flagBits = 0;
        foreach ($this->flags as $flag) {
            $flagBits |= $flag->value;
        }

        return CodecSupport::writer()->writeUnsignedVarLong($this->runtimeEntityId)
            ->writeUnsignedByte($flagBits)
            ->writeFloatLE($this->x)->writeFloatLE($this->y)->writeFloatLE($this->z)
            ->writeUnsignedByte(self::angleToByte($this->pitch))
            ->writeUnsignedByte(self::angleToByte($this->yaw))
            ->writeUnsignedByte(self::angleToByte($this->headYaw))
            ->toString();
    }

    public static function decode(string $bytes): self
    {
        $runtimeEntityId = CodecSupport::reader($bytes)->readUnsignedVarLong();
        $flagBits = $runtimeEntityId->reader->readUnsignedByte();
        if (($flagBits->value & ~self::KNOWN_FLAG_MASK) !== 0) {
            throw new MalformedDataException('Move-actor header contains unknown flag bits.');
        }

        $coordinates = [];
        $reader = $flagBits->reader;
        foreach (['x', 'y', 'z'] as $field) {
            $coordinate = $reader->readFloatLE();
            CodecSupport::validateFiniteFloat($coordinate->value, "Move-actor {$field}", true);
            $coordinates[] = $coordinate->value;
            $reader = $coordinate->reader;
        }

        $rotations = [];
        for ($index = 0; $index < 3; ++$index) {
            $rotation = $reader->readUnsignedByte();
            $rotations[] = $rotation->value * self::DEGREES_PER_BYTE;
            $reader = $rotation->reader;
        }
        CodecSupport::requireEnd($reader);

        $flags = [];
        foreach (MoveActorAbsoluteFlag::cases() as $flag) {
            if (($flagBits->value & $flag->value) !== 0) {
                $flags[] = $flag;
            }
        }

        return new self(
            $runtimeEntityId->value,
            $coordinates[0], $coordinates[1], $coordinates[2],
            $rotations[0], $rotations[1], $rotations[2],
            $flags,
        );
    }

    private static function angleToByte(float $degrees): int
    {
        $scaled = (int) ($degrees / self::DEGREES_PER_BYTE);
        return (($scaled % 256) + 256) % 256;
    }
}
