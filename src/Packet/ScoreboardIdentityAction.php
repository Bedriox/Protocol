<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

enum ScoreboardIdentityAction: int
{
    case REGISTER = 0;
    case CLEAR = 1;
}
