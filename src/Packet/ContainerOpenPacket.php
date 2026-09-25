<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

use Bedriox\Protocol\Exception\InvalidValueException;
use Bedriox\Protocol\Exception\MalformedDataException;

/** Immutable clientbound request to open a container screen. */
final readonly class ContainerOpenPacket implements Packet
{
    public function __construct(
        public int $containerId,
        public ContainerType $containerType,
        public BlockPosition $position,
        public int $actorUniqueId,
    ) {
        if ($containerId < 0 || $containerId > 0xff) {
            throw new InvalidValueException('Container-open ID must fit an unsigned byte.');
        }
    }

    public static function mainPlayerInventory(int $containerId, int $actorUniqueId): self
    {
        return new self(
            $containerId,
            ContainerType::Inventory,
            new BlockPosition(0, 0, 0),
            $actorUniqueId,
        );
    }

    public static function blockInventory(int $containerId, ContainerType $containerType, BlockPosition $position): self
    {
        return new self($containerId, $containerType, $position, -1);
    }

    public function packetId(): int { return PacketIds::CONTAINER_OPEN; }

    public function encode(): string
    {
        return CodecSupport::writer()->writeUnsignedByte($this->containerId)
            ->writeUnsignedByte($this->containerType->value & 0xff)
            ->writeSignedVarInt($this->position->x)->writeSignedVarInt($this->position->y)
            ->writeSignedVarInt($this->position->z)->writeSignedVarLong($this->actorUniqueId)->toString();
    }

    public static function decode(string $bytes): self
    {
        $containerId = CodecSupport::reader($bytes)->readUnsignedByte();
        $typeByte = $containerId->reader->readUnsignedByte();
        $signedType = $typeByte->value > 0x7f ? $typeByte->value - 0x100 : $typeByte->value;
        $containerType = ContainerType::tryFrom($signedType)
            ?? throw new MalformedDataException('Container-open type is unknown.');
        $x = $typeByte->reader->readSignedVarInt();
        $y = $x->reader->readSignedVarInt();
        $z = $y->reader->readSignedVarInt();
        $actorUniqueId = $z->reader->readSignedVarLong();
        CodecSupport::requireEnd($actorUniqueId->reader);
        return new self(
            $containerId->value,
            $containerType,
            new BlockPosition($x->value, $y->value, $z->value),
            $actorUniqueId->value,
        );
    }
}
