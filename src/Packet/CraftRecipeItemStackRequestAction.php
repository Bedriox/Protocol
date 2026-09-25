<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

use Bedriox\Protocol\Exception\InvalidValueException;

final readonly class CraftRecipeItemStackRequestAction implements ItemStackRequestAction
{
    public const int TYPE_ID = ItemStackRequestActionType::CraftRecipe->value;

    public function __construct(public int $recipeNetworkId, public int $requestedCrafts)
    {
        if ($recipeNetworkId < 1 || $recipeNetworkId > 0xffffffff
            || $requestedCrafts < 1 || $requestedCrafts > 0xff) {
            throw new InvalidValueException('Craft-recipe action contains an out-of-range value.');
        }
    }

    public function typeId(): int { return self::TYPE_ID; }
}
