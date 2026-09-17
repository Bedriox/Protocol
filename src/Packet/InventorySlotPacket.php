<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

use Bedriox\Protocol\Exception\InvalidValueException;
use Bedriox\Protocol\Exception\MalformedDataException;

/** A bounded authoritative correction for one inventory slot. */
final readonly class InventorySlotPacket implements Packet
{
    public function __construct(
        public int $containerId,
        public int $slot,
        public InventoryItemStack $item,
        public ?FullContainerName $containerName = null,
        public ?InventoryItemStack $storageItem = null,
    ) {
        if ($containerId < 0 || $containerId > 0xff || $slot < 0 || $slot > 0xffffffff) {
            throw new InvalidValueException('Inventory slot packet contains an out-of-range container or slot.');
        }
    }

    public function packetId(): int { return PacketIds::INVENTORY_SLOT; }

    public function encode(): string
    {
        $writer = CodecSupport::writer()->writeUnsignedVarInt($this->containerId)
            ->writeUnsignedVarInt($this->slot);
        $writer = CodecSupport::writeBoolean($writer, $this->containerName !== null);
        if ($this->containerName !== null) {
            $writer = FullContainerNameWireCodec::write($writer, $this->containerName);
        }
        $writer = CodecSupport::writeBoolean($writer, $this->storageItem !== null);
        if ($this->storageItem !== null) {
            $writer = InventoryItemStackWireCodec::write($writer, $this->storageItem);
        }
        return InventoryItemStackWireCodec::write($writer, $this->item)->toString();
    }

    public static function decode(string $bytes): self
    {
        $containerId = CodecSupport::reader($bytes)->readUnsignedVarInt();
        if ($containerId->value > 0xff) {
            throw new MalformedDataException('Inventory slot container ID exceeds its byte range.');
        }
        $slot = $containerId->reader->readUnsignedVarInt();
        [$hasContainerName, $reader] = CodecSupport::readBoolean($slot->reader);
        $containerName = null;
        if ($hasContainerName) {
            [$containerName, $reader] = FullContainerNameWireCodec::read($reader);
        }
        [$hasStorageItem, $reader] = CodecSupport::readBoolean($reader);
        $storageItem = null;
        if ($hasStorageItem) {
            [$storageItem, $reader] = InventoryItemStackWireCodec::read($reader);
        }
        [$item, $reader] = InventoryItemStackWireCodec::read($reader);
        CodecSupport::requireEnd($reader);
        try {
            return new self($containerId->value, $slot->value, $item, $containerName, $storageItem);
        } catch (InvalidValueException $e) {
            throw new MalformedDataException('Inventory slot correction is invalid.', previous: $e);
        }
    }
}
