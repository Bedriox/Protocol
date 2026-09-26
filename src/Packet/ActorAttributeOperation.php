<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

enum ActorAttributeOperation: int
{
    case Addition = 0;
    case MultiplyBase = 1;
    case MultiplyTotal = 2;
    case Cap = 3;
    case Invalid = 4;
}
