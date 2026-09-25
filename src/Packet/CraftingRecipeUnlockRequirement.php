<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

use Bedriox\Protocol\Exception\InvalidValueException;

final readonly class CraftingRecipeUnlockRequirement
{
    public const int MAXIMUM_INGREDIENTS = 16;

    /** @param list<CraftingRecipeIngredient> $ingredients */
    public function __construct(public RecipeUnlockingContext $context, public array $ingredients = [])
    {
        if (!array_is_list($ingredients) || count($ingredients) > self::MAXIMUM_INGREDIENTS
            || ($context !== RecipeUnlockingContext::None && $ingredients !== [])) {
            throw new InvalidValueException('Crafting recipe unlock requirement is invalid.');
        }
        foreach ($ingredients as $ingredient) {
            if (!$ingredient instanceof CraftingRecipeIngredient || $ingredient->type === CraftingRecipeIngredientType::Empty) {
                throw new InvalidValueException('Crafting recipe unlock requirement contains an invalid ingredient.');
            }
        }
    }

    public static function none(): self
    {
        return new self(RecipeUnlockingContext::None);
    }
}
