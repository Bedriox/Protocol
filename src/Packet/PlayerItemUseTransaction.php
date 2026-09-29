<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

use Bedriox\Protocol\Exception\InvalidValueException;

/** Bounded read-only projection of an item-use transaction embedded in PlayerAuthInput. */
final readonly class PlayerItemUseTransaction
{
    /** @var list<InventoryAction> */
    public array $inventoryActions;
    public InventoryItemStack $item;

    /** @param list<InventoryAction> $inventoryActions */
    public function __construct(
        public int $legacyRequestId,
        public int $legacySlotCount,
        public int $inventoryActionCount,
        public int $actionType,
        public int $triggerType,
        public BlockPosition $blockPosition,
        public int $blockFace,
        public int $hotbarSlot,
        public int $hand,
        public int $itemRuntimeId,
        public int $itemCount,
        public int $itemAux,
        public ?int $itemStackNetworkId,
        public int $itemBlockRuntimeId,
        public float $playerX,
        public float $playerY,
        public float $playerZ,
        public float $clickX,
        public float $clickY,
        public float $clickZ,
        public int $targetBlockRuntimeId,
        public int $predictedResult,
        public int $clientCooldownState,
        array $inventoryActions = [],
        ?InventoryItemStack $typedItem = null,
    ) {
        if ($legacyRequestId < -0x80000000 || $legacyRequestId > 0x7fffffff
            || $legacySlotCount < 0 || $legacySlotCount > 1_024
            || $inventoryActionCount < 0 || $inventoryActionCount > 100
            || $actionType < 0 || $actionType > 3 || $triggerType < 0 || $triggerType > 2
            || $blockFace < 0 || $blockFace > 0xff
            || $hotbarSlot < -0x80000000 || $hotbarSlot > 0x7fffffff || $hand < 0 || $hand > 1
            || $itemRuntimeId < -0x8000 || $itemRuntimeId > 0x7fff
            || $itemCount < 0 || $itemCount > 0xffff
            || $itemAux < 0 || $itemAux > 0x7fff
            || ($itemStackNetworkId !== null && ($itemStackNetworkId < -0x80000000 || $itemStackNetworkId > 0x7fffffff))
            || $itemBlockRuntimeId < -0x80000000 || $itemBlockRuntimeId > 0x7fffffff
            || $targetBlockRuntimeId < -0x80000000 || $targetBlockRuntimeId > 0x7fffffff
            || $predictedResult < 0 || $predictedResult > 1
            || $clientCooldownState < 0 || $clientCooldownState > 1) {
            throw new InvalidValueException('PlayerAuthInput item-use transaction contains an out-of-range value.');
        }
        CodecSupport::validateCount($inventoryActions, 100, 'PlayerAuthInput inventory actions');
        if (count($inventoryActions) !== $inventoryActionCount) {
            throw new InvalidValueException('PlayerAuthInput inventory-action count does not match its typed actions.');
        }
        foreach ($inventoryActions as $action) {
            if (!$action instanceof InventoryAction) {
                throw new InvalidValueException('PlayerAuthInput inventory actions must be typed values.');
            }
        }
        foreach ([$playerX, $playerY, $playerZ, $clickX, $clickY, $clickZ] as $value) {
            CodecSupport::validateFiniteFloat($value, 'PlayerAuthInput item-use vector');
        }
        $this->inventoryActions = $inventoryActions;
        $this->item = $typedItem ?? new InventoryItemStack(
            $itemRuntimeId,
            $itemCount,
            $itemAux,
            $itemStackNetworkId,
            $itemBlockRuntimeId,
            '',
        );
        if ($this->item->runtimeId !== $itemRuntimeId || $this->item->count !== $itemCount
            || $this->item->aux !== $itemAux || $this->item->stackNetworkId !== $itemStackNetworkId
            || $this->item->blockRuntimeId !== $itemBlockRuntimeId) {
            throw new InvalidValueException('PlayerAuthInput typed hand item does not match its compatibility fields.');
        }
    }
}
