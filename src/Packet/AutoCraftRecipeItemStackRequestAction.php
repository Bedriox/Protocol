<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

use Bedriox\Protocol\Exception\InvalidValueException;

final readonly class AutoCraftRecipeItemStackRequestAction implements ItemStackRequestAction
{
    public const int TYPE_ID = ItemStackRequestActionType::AutoCraftRecipe->value;
    public const int MAXIMUM_INGREDIENTS = 9;

    /** @param list<CraftingRecipeIngredient> $ingredients */
    public function __construct(
        public int $recipeNetworkId,
        public int $requestedCrafts,
        public array $ingredients,
    ) {
        if ($recipeNetworkId < 1 || $recipeNetworkId > 0xffffffff
            || $requestedCrafts < 1 || $requestedCrafts > 0xff
            || !array_is_list($ingredients) || count($ingredients) > self::MAXIMUM_INGREDIENTS) {
            throw new InvalidValueException('Automatic craft-recipe action contains an out-of-range value.');
        }
        foreach ($ingredients as $ingredient) {
            if (!$ingredient instanceof CraftingRecipeIngredient) {
                throw new InvalidValueException('Automatic craft-recipe action contains an invalid ingredient.');
            }
        }
    }

    public function typeId(): int { return self::TYPE_ID; }
}
