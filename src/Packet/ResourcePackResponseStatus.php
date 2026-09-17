<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

enum ResourcePackResponseStatus: int
{
    case Refused = 0;
    case SendPacks = 1;
    case HaveAllPacks = 2;
    case Completed = 3;
}
