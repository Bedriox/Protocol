<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

use Bedriox\Protocol\Exception\InvalidValueException;

final readonly class SmithingTransformRecipe
{
    public function __construct(
        public string $recipeId,
        public CraftingRecipeIngredient $template,
        public CraftingRecipeIngredient $base,
        public CraftingRecipeIngredient $addition,
        public InventoryItemStack $result,
        public string $craftingTag,
        public int $networkId,
    ) {
        CodecSupport::validateString($recipeId, CodecSupport::MAX_SHORT_STRING_BYTES, 'Smithing recipe ID');
        CodecSupport::validateString($craftingTag, CodecSupport::MAX_SHORT_STRING_BYTES, 'Smithing crafting tag');
        if ($recipeId === '' || $craftingTag === '' || $networkId < 1 || $networkId > 0xffffffff) {
            throw new InvalidValueException('Smithing transform recipe contains an invalid identity.');
        }
        foreach ([$template, $base, $addition] as $ingredient) {
            if ($ingredient->type === CraftingRecipeIngredientType::Empty) {
                throw new InvalidValueException('Smithing transform ingredients cannot be empty.');
            }
        }
        CreativeItemStackWireCodec::validate($result);
        if ($result->runtimeId === 0) {
            throw new InvalidValueException('Smithing transform result cannot be empty.');
        }
    }
}
