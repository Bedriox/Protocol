<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

use Bedriox\Protocol\Value\UnsignedLong;

/** Immutable packet-32 armor snapshot; applying it remains server policy. */
final readonly class MobArmorEquipmentPacket implements Packet
{
    public InventoryItemStack $helmet;
    public InventoryItemStack $chestplate;
    public InventoryItemStack $leggings;
    public InventoryItemStack $boots;
    public InventoryItemStack $body;

    public function __construct(
        public UnsignedLong $runtimeEntityId,
        ?InventoryItemStack $helmet = null,
        ?InventoryItemStack $chestplate = null,
        ?InventoryItemStack $leggings = null,
        ?InventoryItemStack $boots = null,
        ?InventoryItemStack $body = null,
    ) {
        $this->helmet = $helmet ?? InventoryItemStack::empty();
        $this->chestplate = $chestplate ?? InventoryItemStack::empty();
        $this->leggings = $leggings ?? InventoryItemStack::empty();
        $this->boots = $boots ?? InventoryItemStack::empty();
        $this->body = $body ?? InventoryItemStack::empty();
    }

    public function packetId(): int
    {
        return PacketIds::MOB_ARMOR_EQUIPMENT;
    }

    public function encode(): string
    {
        $writer = CodecSupport::writer()->writeUnsignedVarLong($this->runtimeEntityId);
        foreach ([$this->helmet, $this->chestplate, $this->leggings, $this->boots, $this->body] as $item) {
            $writer = InventoryItemStackWireCodec::write($writer, $item);
        }

        return $writer->toString();
    }

    public static function decode(string $bytes): self
    {
        $runtimeEntityId = CodecSupport::reader($bytes)->readUnsignedVarLong();
        $items = [];
        $reader = $runtimeEntityId->reader;
        for ($index = 0; $index < 5; ++$index) {
            [$items[], $reader] = InventoryItemStackWireCodec::read($reader);
        }
        CodecSupport::requireEnd($reader);

        return new self($runtimeEntityId->value, ...$items);
    }
}
