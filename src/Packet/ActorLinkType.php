<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

enum ActorLinkType: int
{
    case Remove = 0;
    case Rider = 1;
    case Passenger = 2;
}
