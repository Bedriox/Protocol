<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

use Bedriox\Protocol\Exception\InvalidValueException;

/** Current client request to select the item form of one world block. */
final readonly class BlockPickRequestPacket implements Packet
{
    public function __construct(
        public BlockPosition $position,
        public bool $addUserData,
        public int $hotbarSlot,
    ) {
        if ($hotbarSlot < 0 || $hotbarSlot > 0xff) {
            throw new InvalidValueException('Block-pick hotbar slot must fit an unsigned byte.');
        }
    }

    public function packetId(): int
    {
        return PacketIds::BLOCK_PICK_REQUEST;
    }

    public function encode(): string
    {
        $writer = CodecSupport::writer()
            ->writeSignedVarInt($this->position->x)
            ->writeSignedVarInt($this->position->y)
            ->writeSignedVarInt($this->position->z);

        return CodecSupport::writeBoolean($writer, $this->addUserData)
            ->writeUnsignedByte($this->hotbarSlot)
            ->toString();
    }

    public static function decode(string $bytes): self
    {
        $x = CodecSupport::reader($bytes)->readSignedVarInt();
        $y = $x->reader->readSignedVarInt();
        $z = $y->reader->readSignedVarInt();
        [$addUserData, $reader] = CodecSupport::readBoolean($z->reader);
        $hotbarSlot = $reader->readUnsignedByte();
        CodecSupport::requireEnd($hotbarSlot->reader);

        return new self(new BlockPosition($x->value, $y->value, $z->value), $addUserData, $hotbarSlot->value);
    }
}
