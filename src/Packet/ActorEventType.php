<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

enum ActorEventType: int
{
    case Hurt = 2;
    case Death = 3;
    case UseItem = 9;
    case Respawn = 18;
    case EatingItem = 57;
}
