<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

use Bedriox\Protocol\Exception\InvalidValueException;

final readonly class CraftRepairAndDisenchantItemStackRequestAction implements ItemStackRequestAction
{
    public const int TYPE_ID = ItemStackRequestActionType::CraftRepairAndDisenchant->value;

    public function __construct(
        public int $recipeNetworkId,
        public int $requestedCrafts,
        public int $repairCost,
    ) {
        if ($recipeNetworkId < -0x80000000 || $recipeNetworkId > 0x7fffffff
            || $requestedCrafts < 0 || $requestedCrafts > 0xff
            || $repairCost < -0x80000000 || $repairCost > 0x7fffffff) {
            throw new InvalidValueException('Repair-and-disenchant action contains an out-of-range value.');
        }
    }

    public function typeId(): int { return self::TYPE_ID; }
}
