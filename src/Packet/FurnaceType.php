<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

enum FurnaceType: int
{
    case None = 0;
    case Furnace = 1;
    case BlastFurnace = 2;
    case Smoker = 3;
}
