<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

enum InventoryTransactionType: int
{
    case Normal = 0;
    case InventoryMismatch = 1;
    case ItemUse = 2;
    case ItemUseOnEntity = 3;
    case ItemRelease = 4;
}
