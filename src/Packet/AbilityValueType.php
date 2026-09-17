<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

enum AbilityValueType: int
{
    case Unset = 0;
    case Bool = 1;
    case Float = 2;
}
