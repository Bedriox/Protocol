<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

use Bedriox\Protocol\Exception\InvalidValueException;

final readonly class ShapelessCraftingRecipe implements CraftingRecipe
{
    public const int MAXIMUM_INGREDIENTS = 9;
    public const int MAXIMUM_RESULTS = 16;

    /**
     * @param list<CraftingRecipeIngredient> $ingredients
     * @param list<InventoryItemStack> $results
     */
    public function __construct(
        public CraftingRecipeType $recipeType,
        public string $recipeId,
        public array $ingredients,
        public array $results,
        public string $uuid,
        public string $craftingTag,
        public int $priority,
        public CraftingRecipeUnlockRequirement $unlockRequirement,
        public int $recipeNetworkId,
    ) {
        if (!in_array($recipeType, [
            CraftingRecipeType::Shapeless,
            CraftingRecipeType::UserDataShapeless,
            CraftingRecipeType::ShapelessChemistry,
        ], true)
            || !array_is_list($ingredients) || $ingredients === [] || count($ingredients) > self::MAXIMUM_INGREDIENTS
            || !array_is_list($results) || $results === [] || count($results) > self::MAXIMUM_RESULTS
            || $priority < -0x80000000 || $priority > 0x7fffffff
            || $recipeNetworkId < 1 || $recipeNetworkId > 0xffffffff) {
            throw new InvalidValueException('Shapeless crafting recipe is invalid.');
        }
        CodecSupport::validateString($recipeId, CodecSupport::MAX_SHORT_STRING_BYTES, 'Crafting recipe ID');
        CodecSupport::validateString($craftingTag, CodecSupport::MAX_SHORT_STRING_BYTES, 'Crafting recipe tag');
        if ($recipeId === '') {
            throw new InvalidValueException('Crafting recipe ID cannot be empty.');
        }
        CodecSupport::uuidToWire($uuid);
        foreach ($ingredients as $ingredient) {
            if (!$ingredient instanceof CraftingRecipeIngredient || $ingredient->type === CraftingRecipeIngredientType::Empty) {
                throw new InvalidValueException('Shapeless crafting recipe contains an invalid ingredient.');
            }
        }
        foreach ($results as $result) {
            if (!$result instanceof InventoryItemStack) {
                throw new InvalidValueException('Shapeless crafting recipe contains an invalid result.');
            }
            CreativeItemStackWireCodec::validate($result);
            if ($result->runtimeId === 0) {
                throw new InvalidValueException('Shapeless crafting recipe results cannot be air.');
            }
        }
    }

    public function type(): CraftingRecipeType { return $this->recipeType; }
    public function networkId(): int { return $this->recipeNetworkId; }
}
