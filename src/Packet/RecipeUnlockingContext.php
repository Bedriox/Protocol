<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

enum RecipeUnlockingContext: int
{
    case None = 0;
    case AlwaysUnlocked = 1;
    case PlayerInWater = 2;
    case PlayerHasManyItems = 3;
}
