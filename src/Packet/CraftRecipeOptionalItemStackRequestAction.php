<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

use Bedriox\Protocol\Exception\InvalidValueException;

final readonly class CraftRecipeOptionalItemStackRequestAction implements ItemStackRequestAction
{
    public const int TYPE_ID = ItemStackRequestActionType::CraftRecipeOptional->value;

    public function __construct(public int $recipeNetworkId, public int $filteredStringIndex)
    {
        if ($recipeNetworkId < 1 || $recipeNetworkId > 0xffffffff
            || $filteredStringIndex < -0x80000000 || $filteredStringIndex > 0x7fffffff) {
            throw new InvalidValueException('Optional craft-recipe action contains an out-of-range value.');
        }
    }

    public function typeId(): int { return self::TYPE_ID; }
}
