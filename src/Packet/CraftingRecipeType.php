<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

enum CraftingRecipeType: int
{
    case Shapeless = 0;
    case Shaped = 1;
    case Multi = 2;
    case UserDataShapeless = 3;
    case ShapelessChemistry = 4;
    case ShapedChemistry = 5;
}
