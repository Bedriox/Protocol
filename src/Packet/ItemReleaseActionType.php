<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

enum ItemReleaseActionType: int
{
    case Release = 0;
    case Use = 1;
}
