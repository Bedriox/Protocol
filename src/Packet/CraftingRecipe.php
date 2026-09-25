<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

interface CraftingRecipe
{
    public function type(): CraftingRecipeType;

    public function networkId(): int;
}
