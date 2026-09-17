<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

enum InventorySourceFlag: int
{
    case DropItem = 0;
    case PickupItem = 1;
    case None = 2;
}
