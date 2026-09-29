<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

final readonly class ItemUseInventoryTransaction implements InventoryTransactionData
{
    public function __construct(
        public ItemUseActionType $action,
        public ItemUseTriggerType $trigger,
        public BlockPosition $blockPosition,
        public int $blockFace,
        public int $hotbarSlot,
        public HandSlot $hand,
        public InventoryItemStack $item,
        public InventoryVector3 $playerPosition,
        public InventoryVector3 $clickPosition,
        public int $targetBlockRuntimeId,
        public ItemUsePredictedResult $predictedResult,
        public ItemUseClientCooldownState $cooldownState,
    ) {
        if ($blockFace < 0 || $blockFace > 0xff || $hotbarSlot < -0x80000000 || $hotbarSlot > 0x7fffffff
            || $targetBlockRuntimeId < -0x80000000 || $targetBlockRuntimeId > 0x7fffffff) {
            throw new \Bedriox\Protocol\Exception\InvalidValueException('Item-use transaction contains an out-of-range scalar.');
        }
    }

    public function type(): InventoryTransactionType
    {
        return InventoryTransactionType::ItemUse;
    }
}
