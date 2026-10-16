<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

/** Display slots accepted by the current Bedrock scoreboard wire contract. */
enum ScoreboardDisplaySlot: string
{
    case SIDEBAR = 'sidebar';
    case PLAYER_LIST = 'list';
    case BELOW_NAME = 'belowname';
}
