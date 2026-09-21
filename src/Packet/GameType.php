<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

/** Current Bedrock game-mode identifiers. */
enum GameType: int
{
    case Survival = 0;
    case Creative = 1;
    case Adventure = 2;
    case SurvivalViewer = 3;
    case CreativeViewer = 4;
    case Default = 5;
    case Spectator = 6;
}
