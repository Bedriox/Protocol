<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

enum LevelEventBlockFace: int
{
    case Down = 0;
    case Up = 1;
    case North = 2;
    case South = 3;
    case West = 4;
    case East = 5;
}
