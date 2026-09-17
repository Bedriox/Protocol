<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

use Bedriox\Protocol\Exception\InvalidValueException;
use Bedriox\Protocol\Exception\MalformedDataException;

/** A bounded authoritative inventory snapshot. */
final readonly class InventoryContentPacket implements Packet
{
    public const int MAXIMUM_SLOTS = 128;

    /** @var list<InventoryItemStack> */
    public array $items;
    public FullContainerName $containerName;
    public InventoryItemStack $storageItem;

    /**
     * Passing a slot count retains the established empty-inventory convenience API.
     *
     * @param list<InventoryItemStack>|int $items
     */
    public function __construct(
        public int $windowId,
        array|int $items,
        ?FullContainerName $containerName = null,
        ?InventoryItemStack $storageItem = null,
    ) {
        if ($windowId < 0 || $windowId > 0xffffffff) {
            throw new InvalidValueException('Inventory window ID is outside its bounded range.');
        }
        if (is_int($items)) {
            if ($items < 0 || $items > self::MAXIMUM_SLOTS) {
                throw new InvalidValueException('Inventory slot count is outside its bounded range.');
            }
            $items = array_fill(0, $items, self::emptySlot());
        }
        CodecSupport::validateCount($items, self::MAXIMUM_SLOTS, 'Inventory content');
        foreach ($items as $item) {
            if (!$item instanceof InventoryItemStack) {
                throw new InvalidValueException('Inventory content must contain item stacks.');
            }
        }
        $this->items = $items;
        $this->containerName = $containerName ?? new FullContainerName();
        $this->storageItem = $storageItem ?? self::emptySlot();
    }

    public function packetId(): int { return PacketIds::INVENTORY_CONTENT; }

    public function encode(): string
    {
        $writer = CodecSupport::writer()->writeUnsignedVarInt($this->windowId)
            ->writeUnsignedVarInt(count($this->items));
        foreach ($this->items as $item) {
            $writer = InventoryItemStackWireCodec::write($writer, $item);
        }
        $writer = FullContainerNameWireCodec::write($writer, $this->containerName);
        return InventoryItemStackWireCodec::write($writer, $this->storageItem)->toString();
    }

    public static function decode(string $bytes): self
    {
        $windowId = CodecSupport::reader($bytes)->readUnsignedVarInt();
        $count = $windowId->reader->readUnsignedVarInt();
        if ($count->value > self::MAXIMUM_SLOTS) {
            throw new MalformedDataException('Inventory content slot count exceeds its limit.');
        }
        $items = [];
        $reader = $count->reader;
        for ($index = 0; $index < $count->value; ++$index) {
            [$items[], $reader] = InventoryItemStackWireCodec::read($reader);
        }
        [$containerName, $reader] = FullContainerNameWireCodec::read($reader);
        [$storageItem, $reader] = InventoryItemStackWireCodec::read($reader);
        CodecSupport::requireEnd($reader);
        try {
            return new self($windowId->value, $items, $containerName, $storageItem);
        } catch (InvalidValueException $e) {
            throw new MalformedDataException('Inventory content is invalid.', previous: $e);
        }
    }

    public static function emptySlot(): InventoryItemStack
    {
        // Preserve the retail-qualified empty snapshot representation byte-for-byte.
        return new InventoryItemStack(0, 1, 0, null, 0, '');
    }
}
