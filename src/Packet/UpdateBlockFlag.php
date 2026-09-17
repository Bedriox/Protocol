<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

enum UpdateBlockFlag: int
{
    case Neighbors = 0;
    case Network = 1;
    case NoGraphic = 2;
    case Unused = 3;
    case Priority = 4;
}
