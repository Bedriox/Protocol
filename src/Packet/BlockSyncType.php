<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

/** Associates an authoritative block mutation with an actor lifecycle transition. */
enum BlockSyncType: int
{
    case NONE = 0;
    case CREATE = 1;
    case DESTROY = 2;
}
