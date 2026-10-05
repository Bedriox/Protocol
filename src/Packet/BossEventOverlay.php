<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

/** Visual divisions supported by the protocol-2193 boss-event schema. */
enum BossEventOverlay: int
{
    case PROGRESS = 0;
    case NOTCHED_6 = 1;
    case NOTCHED_10 = 2;
    case NOTCHED_12 = 3;
    case NOTCHED_20 = 4;
}
