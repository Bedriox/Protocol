<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

enum CursorItemDragState: int
{
    case Start = 0;
    case Stop = 1;
}
