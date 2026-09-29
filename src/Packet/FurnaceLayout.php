<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

enum FurnaceLayout: int
{
    case None = 0;
    case Normal = 1;
    case Compact = 2;
}
