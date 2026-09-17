<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

use Bedriox\Protocol\Exception\InvalidValueException;
use Bedriox\Protocol\Value\UnsignedLong;

/** Immutable packet-31 equipment notification; applying it remains server policy. */
final readonly class MobEquipmentPacket implements Packet
{
    public InventoryItemStack $item;

    public function __construct(
        public UnsignedLong $runtimeEntityId,
        public int $inventorySlot = 0,
        public int $hotbarSlot = 0,
        public int $windowId = 0,
        ?InventoryItemStack $item = null,
    ) {
        foreach ([$inventorySlot, $hotbarSlot, $windowId] as $value) {
            if ($value < 0 || $value > 255) {
                throw new InvalidValueException('Equipment slot and window values must fit one byte.');
            }
        }
        $this->item = $item ?? InventoryItemStack::empty();
    }

    public function packetId(): int { return PacketIds::MOB_EQUIPMENT; }

    public function encode(): string
    {
        $writer = CodecSupport::writer()->writeUnsignedVarLong($this->runtimeEntityId);
        return InventoryItemStackWireCodec::write($writer, $this->item)
            ->writeUnsignedByte($this->inventorySlot)->writeUnsignedByte($this->hotbarSlot)
            ->writeUnsignedByte($this->windowId)->toString();
    }

    public static function decode(string $bytes): self
    {
        $runtime = CodecSupport::reader($bytes)->readUnsignedVarLong();
        [$item, $reader] = InventoryItemStackWireCodec::read($runtime->reader);
        $inventory = $reader->readUnsignedByte();
        $hotbar = $inventory->reader->readUnsignedByte();
        $window = $hotbar->reader->readUnsignedByte();
        CodecSupport::requireEnd($window->reader);

        return new self($runtime->value, $inventory->value, $hotbar->value, $window->value, $item);
    }
}
