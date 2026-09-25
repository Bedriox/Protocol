<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

use Bedriox\Protocol\Exception\InvalidValueException;

final readonly class MultiCraftingRecipe implements CraftingRecipe
{
    public function __construct(public string $uuid, public int $recipeNetworkId)
    {
        CodecSupport::uuidToWire($uuid);
        if ($recipeNetworkId < 1 || $recipeNetworkId > 0xffffffff) {
            throw new InvalidValueException('Multi crafting recipe network ID is out of range.');
        }
    }

    public function type(): CraftingRecipeType { return CraftingRecipeType::Multi; }
    public function networkId(): int { return $this->recipeNetworkId; }
}
