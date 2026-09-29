<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

use Bedriox\Protocol\Exception\InvalidValueException;

final readonly class SmithingTrimRecipe
{
    public function __construct(
        public string $recipeId,
        public CraftingRecipeIngredient $template,
        public CraftingRecipeIngredient $base,
        public CraftingRecipeIngredient $addition,
        public string $craftingTag,
        public int $networkId,
    ) {
        CodecSupport::validateString($recipeId, CodecSupport::MAX_SHORT_STRING_BYTES, 'Smithing recipe ID');
        CodecSupport::validateString($craftingTag, CodecSupport::MAX_SHORT_STRING_BYTES, 'Smithing crafting tag');
        if ($recipeId === '' || $craftingTag === '' || $networkId < 1 || $networkId > 0xffffffff) {
            throw new InvalidValueException('Smithing trim recipe contains an invalid identity.');
        }
        foreach ([$template, $base, $addition] as $ingredient) {
            if ($ingredient->type === CraftingRecipeIngredientType::Empty) {
                throw new InvalidValueException('Smithing trim ingredients cannot be empty.');
            }
        }
    }
}
