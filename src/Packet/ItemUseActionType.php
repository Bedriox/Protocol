<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

enum ItemUseActionType: int
{
    case Place = 0;
    case Use = 1;
    case Destroy = 2;
    case UseAsAttack = 3;
}
