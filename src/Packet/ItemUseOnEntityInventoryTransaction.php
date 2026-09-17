<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

use Bedriox\Protocol\Exception\InvalidValueException;
use Bedriox\Protocol\Value\UnsignedLong;

final readonly class ItemUseOnEntityInventoryTransaction implements InventoryTransactionData
{
    public function __construct(
        public UnsignedLong $runtimeEntityId,
        public ItemUseOnEntityActionType $action,
        public int $hotbarSlot,
        public InventoryItemStack $item,
        public InventoryVector3 $playerPosition,
        public InventoryVector3 $clickPosition,
    ) {
        if ($hotbarSlot < -0x80000000 || $hotbarSlot > 0x7fffffff) {
            throw new InvalidValueException('Item-use-on-entity hotbar slot must fit a signed 32-bit integer.');
        }
    }

    public function type(): InventoryTransactionType
    {
        return InventoryTransactionType::ItemUseOnEntity;
    }
}
