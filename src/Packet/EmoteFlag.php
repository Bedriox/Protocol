<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

enum EmoteFlag: int
{
    case ServerSide = 0;
    case MuteEmoteChat = 1;
}
