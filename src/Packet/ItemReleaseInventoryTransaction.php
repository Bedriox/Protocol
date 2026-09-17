<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

use Bedriox\Protocol\Exception\InvalidValueException;

final readonly class ItemReleaseInventoryTransaction implements InventoryTransactionData
{
    public function __construct(
        public ItemReleaseActionType $action,
        public int $hotbarSlot,
        public InventoryItemStack $item,
        public InventoryVector3 $headPosition,
    ) {
        if ($hotbarSlot < -0x80000000 || $hotbarSlot > 0x7fffffff) {
            throw new InvalidValueException('Item-release hotbar slot must fit a signed 32-bit integer.');
        }
    }

    public function type(): InventoryTransactionType
    {
        return InventoryTransactionType::ItemRelease;
    }
}
