<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

use Bedriox\Protocol\Exception\InvalidValueException;

final readonly class ShapedCraftingRecipe implements CraftingRecipe
{
    public const int MAXIMUM_RESULTS = 16;

    /**
     * @param list<CraftingRecipeIngredient> $ingredients
     * @param list<InventoryItemStack> $results
     */
    public function __construct(
        public CraftingRecipeType $recipeType,
        public string $recipeId,
        public int $width,
        public int $height,
        public array $ingredients,
        public array $results,
        public string $uuid,
        public string $craftingTag,
        public int $priority,
        public bool $assumeSymmetry,
        public CraftingRecipeUnlockRequirement $unlockRequirement,
        public int $recipeNetworkId,
    ) {
        if (!in_array($recipeType, [CraftingRecipeType::Shaped, CraftingRecipeType::ShapedChemistry], true)
            || $width < 1 || $width > 3 || $height < 1 || $height > 3
            || !array_is_list($ingredients) || count($ingredients) !== $width * $height
            || !array_is_list($results) || $results === [] || count($results) > self::MAXIMUM_RESULTS
            || $priority < -0x80000000 || $priority > 0x7fffffff
            || $recipeNetworkId < 1 || $recipeNetworkId > 0xffffffff) {
            throw new InvalidValueException('Shaped crafting recipe is invalid.');
        }
        self::validateCommon($recipeId, $uuid, $craftingTag, $ingredients, $results);
    }

    public function type(): CraftingRecipeType { return $this->recipeType; }
    public function networkId(): int { return $this->recipeNetworkId; }

    /**
     * @param list<CraftingRecipeIngredient> $ingredients
     * @param list<InventoryItemStack> $results
     */
    private static function validateCommon(string $recipeId, string $uuid, string $craftingTag, array $ingredients, array $results): void
    {
        CodecSupport::validateString($recipeId, CodecSupport::MAX_SHORT_STRING_BYTES, 'Crafting recipe ID');
        CodecSupport::validateString($craftingTag, CodecSupport::MAX_SHORT_STRING_BYTES, 'Crafting recipe tag');
        if ($recipeId === '') {
            throw new InvalidValueException('Crafting recipe ID cannot be empty.');
        }
        CodecSupport::uuidToWire($uuid);
        foreach ($ingredients as $ingredient) {
            if (!$ingredient instanceof CraftingRecipeIngredient) {
                throw new InvalidValueException('Shaped crafting recipe contains an invalid ingredient.');
            }
        }
        foreach ($results as $result) {
            if (!$result instanceof InventoryItemStack) {
                throw new InvalidValueException('Shaped crafting recipe contains an invalid result.');
            }
            CreativeItemStackWireCodec::validate($result);
            if ($result->runtimeId === 0) {
                throw new InvalidValueException('Shaped crafting recipe results cannot be air.');
            }
        }
    }
}
