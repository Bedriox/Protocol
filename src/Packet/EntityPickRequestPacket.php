<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

use Bedriox\Protocol\Exception\InvalidValueException;

/** Current client request to select the item represented by one entity. */
final readonly class EntityPickRequestPacket implements Packet
{
    public function __construct(
        public int $runtimeEntityId,
        public int $hotbarSlot,
        public bool $withData,
    ) {
        if ($hotbarSlot < 0 || $hotbarSlot > 0xff) {
            throw new InvalidValueException('Entity-pick hotbar slot must fit an unsigned byte.');
        }
    }

    public function packetId(): int
    {
        return PacketIds::ENTITY_PICK_REQUEST;
    }

    public function encode(): string
    {
        $writer = CodecSupport::writer()
            ->writeSignedLongLE($this->runtimeEntityId)
            ->writeUnsignedByte($this->hotbarSlot);

        return CodecSupport::writeBoolean($writer, $this->withData)->toString();
    }

    public static function decode(string $bytes): self
    {
        $runtimeEntityId = CodecSupport::reader($bytes)->readSignedLongLE();
        $hotbarSlot = $runtimeEntityId->reader->readUnsignedByte();
        [$withData, $reader] = CodecSupport::readBoolean($hotbarSlot->reader);
        CodecSupport::requireEnd($reader);

        return new self($runtimeEntityId->value, $hotbarSlot->value, $withData);
    }
}
