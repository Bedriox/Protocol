<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

enum ItemUseOnEntityActionType: int
{
    case Interact = 0;
    case Attack = 1;
    case ItemInteract = 2;
}
