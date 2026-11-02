<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

enum MatchmakingState: int
{
    case Idle = 0;
    case Matchmaking = 1;
    case MatchFound = 2;
    case Canceled = 3;
    case PlayerLeftParty = 4;
    case PlayerLeftServer = 5;
    case ServerShutdown = 6;
    case TimedOut = 7;
    case RequeueAsParty = 8;
}
