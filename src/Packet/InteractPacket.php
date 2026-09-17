<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

use Bedriox\Protocol\Exception\InvalidValueException;
use Bedriox\Protocol\Exception\MalformedDataException;
use Bedriox\Protocol\Value\UnsignedLong;

/** Bounded Bedrock client interaction notification. */
final readonly class InteractPacket implements Packet
{
    public const int INVALID = 0;
    public const int VEHICLE_EXIT = 3;
    public const int MOUSEOVER = 4;
    public const int OPEN_NPC = 5;
    public const int OPEN_INVENTORY = 6;

    public function __construct(
        public int $action,
        public UnsignedLong $targetRuntimeId,
        public ?float $x = null,
        public ?float $y = null,
        public ?float $z = null,
    ) {
        if ($action < self::INVALID || $action > self::OPEN_INVENTORY) {
            throw new InvalidValueException('Interact action is invalid.');
        }
        if (($x === null) !== ($y === null) || ($x === null) !== ($z === null)) {
            throw new InvalidValueException('Interact position must be complete or absent.');
        }
        foreach ([$x, $y, $z] as $coordinate) {
            if ($coordinate !== null) {
                CodecSupport::validateFiniteFloat($coordinate, 'Interact position');
            }
        }
    }

    public function packetId(): int
    {
        return PacketIds::INTERACT;
    }

    public function encode(): string
    {
        $writer = CodecSupport::writer()->writeUnsignedByte($this->action)->writeUnsignedVarLong($this->targetRuntimeId);
        $writer = CodecSupport::writeBoolean($writer, $this->x !== null);
        if ($this->x !== null && $this->y !== null && $this->z !== null) {
            $writer = $writer->writeFloatLE($this->x)->writeFloatLE($this->y)->writeFloatLE($this->z);
        }

        return $writer->toString();
    }

    public static function decode(string $bytes): self
    {
        $action = CodecSupport::reader($bytes)->readUnsignedByte();
        if ($action->value < self::INVALID || $action->value > self::OPEN_INVENTORY) {
            throw new MalformedDataException('Interact action is invalid.');
        }
        $target = $action->reader->readUnsignedVarLong();
        [$hasPosition, $reader] = CodecSupport::readBoolean($target->reader);
        $x = $y = $z = null;
        if ($hasPosition) {
            $readX = $reader->readFloatLE();
            $readY = $readX->reader->readFloatLE();
            $readZ = $readY->reader->readFloatLE();
            $x = $readX->value;
            $y = $readY->value;
            $z = $readZ->value;
            $reader = $readZ->reader;
        }
        CodecSupport::requireEnd($reader);

        return new self($action->value, $target->value, $x, $y, $z);
    }
}
