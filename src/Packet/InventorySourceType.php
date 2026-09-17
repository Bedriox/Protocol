<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

enum InventorySourceType: int
{
    case Container = 0;
    case Global = 1;
    case WorldInteraction = 2;
    case Creative = 3;
    case UntrackedInteractionUi = 100;
    case NonImplementedTodo = 99_999;
}
