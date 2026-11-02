<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

enum PassengerOfBlockEmoteType: int
{
    case Standing = 0;
    case Riding = 1;
    case Laying = 2;
}
