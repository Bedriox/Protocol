<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

use Bedriox\Protocol\Exception\InvalidValueException;

final readonly class InventorySource
{
    public function __construct(
        public InventorySourceType $type,
        public ?int $containerId = null,
        public ?InventorySourceFlag $flag = null,
    ) {
        $needsContainer = $type === InventorySourceType::Container || $type === InventorySourceType::NonImplementedTodo;
        if (($containerId !== null) !== $needsContainer || ($containerId !== null && ($containerId < -128 || $containerId > 127))) {
            throw new InvalidValueException('Inventory source container ID does not match its source type.');
        }
        if (($flag !== null) !== ($type === InventorySourceType::WorldInteraction)) {
            throw new InvalidValueException('Inventory source flag does not match its source type.');
        }
    }
}
