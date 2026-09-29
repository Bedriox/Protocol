<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

enum MapTrackedObjectType: int
{
    case Entity = 0;
    case Block = 1;
}
