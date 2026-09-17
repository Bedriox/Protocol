<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

enum HandSlot: int
{
    case Mainhand = 0;
    case Offhand = 1;
}
