<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

use Bedriox\Protocol\Exception\InvalidValueException;

/** Clientbound progress-property update for one open container. */
final readonly class ContainerSetDataPacket implements Packet
{
    public function __construct(
        public int $containerId,
        public int $property,
        public int $value,
    ) {
        if ($this->containerId < 0 || $this->containerId > 0xff) {
            throw new InvalidValueException('Container-data ID must fit an unsigned byte.');
        }
        foreach ([$this->property, $this->value] as $field) {
            if ($field < -0x80000000 || $field > 0x7fffffff) {
                throw new InvalidValueException('Container-data fields must fit signed 32-bit integers.');
            }
        }
    }

    public static function brewingStand(int $containerId, BrewingStandProperty $property, int $value): self
    {
        return new self($containerId, $property->value, $value);
    }

    public static function furnace(int $containerId, FurnaceProperty $property, int $value): self
    {
        return new self($containerId, $property->value, $value);
    }

    public function packetId(): int
    {
        return PacketIds::CONTAINER_SET_DATA;
    }

    public function encode(): string
    {
        return CodecSupport::writer()
            ->writeUnsignedByte($this->containerId)
            ->writeSignedVarInt($this->property)
            ->writeSignedVarInt($this->value)
            ->toString();
    }

    public static function decode(string $bytes): self
    {
        $containerId = CodecSupport::reader($bytes)->readUnsignedByte();
        $property = $containerId->reader->readSignedVarInt();
        $value = $property->reader->readSignedVarInt();
        CodecSupport::requireEnd($value->reader);

        return new self($containerId->value, $property->value, $value->value);
    }
}
