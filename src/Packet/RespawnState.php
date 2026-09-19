<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

enum RespawnState: int
{
    case ServerSearching = 0;
    case ServerReady = 1;
    case ClientReady = 2;
}
