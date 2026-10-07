<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

use Bedriox\Protocol\Exception\InvalidValueException;
use Bedriox\Protocol\Exception\MalformedDataException;

/** Declares the equipment-slot layout for a horse-family container window. */
final readonly class UpdateEquipPacket implements Packet
{
    public function __construct(
        public int $containerId,
        public ContainerType $containerType,
        public int $size,
        public int $actorUniqueId,
        public string $networkNbt,
    ) {
        if ($containerId < 0 || $containerId > 0xff || $containerType !== ContainerType::Horse
            || $size < 0 || $size > 54) {
            throw new InvalidValueException('Update-equipment window fields are outside their supported bounds.');
        }
        NetworkNbtCompound::validate($networkNbt);
    }

    public function packetId(): int
    {
        return PacketIds::UPDATE_EQUIP;
    }

    public function encode(): string
    {
        return CodecSupport::writer()
            ->writeUnsignedByte($this->containerId)
            ->writeUnsignedByte($this->containerType->value)
            ->writeSignedVarInt($this->size)
            ->writeSignedVarLong($this->actorUniqueId)
            ->writeBytes($this->networkNbt)
            ->toString();
    }

    public static function decode(string $bytes): self
    {
        $containerId = CodecSupport::reader($bytes)->readUnsignedByte();
        $containerType = $containerId->reader->readUnsignedByte();
        if ($containerType->value !== ContainerType::Horse->value) {
            throw new MalformedDataException('Update-equipment container type is unsupported.');
        }
        $size = $containerType->reader->readSignedVarInt();
        $actorUniqueId = $size->reader->readSignedVarLong();
        $networkNbt = $actorUniqueId->reader->readBytes($actorUniqueId->reader->remaining());
        try {
            return new self(
                $containerId->value,
                ContainerType::Horse,
                $size->value,
                $actorUniqueId->value,
                $networkNbt->value,
            );
        } catch (InvalidValueException $error) {
            throw new MalformedDataException('Update-equipment payload is invalid.', previous: $error);
        }
    }
}
