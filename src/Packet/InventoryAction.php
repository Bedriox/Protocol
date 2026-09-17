<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

use Bedriox\Protocol\Exception\InvalidValueException;

final readonly class InventoryAction
{
    public function __construct(
        public InventorySource $source,
        public int $slot,
        public InventoryItemStack $fromItem,
        public InventoryItemStack $toItem,
    ) {
        if ($slot < 0 || $slot > 0xffffffff) {
            throw new InvalidValueException('Inventory action slot must fit an unsigned 32-bit integer.');
        }
    }
}
