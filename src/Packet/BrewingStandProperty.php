<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

/** Brewing-stand progress properties synchronized to an open container. */
enum BrewingStandProperty: int
{
    case BrewTime = 0;
    case FuelAmount = 1;
    case FuelTotal = 2;
}
