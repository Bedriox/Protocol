<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

enum FurnaceLeftTab: int
{
    case Search = 0;
    case Equipment = 1;
    case Nature = 2;
    case Items = 3;
}
