<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

/** Furnace-family progress properties synchronized to an open container. */
enum FurnaceProperty: int
{
    case TickCount = 0;
    case LitTime = 1;
    case LitDuration = 2;
    case StoredExperience = 3;
    case FuelAuxiliaryValue = 4;
}
