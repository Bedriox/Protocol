<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

enum PlayerPermission: int
{
    case Visitor = 0;
    case Member = 1;
    case Operator = 2;
    case Custom = 3;
}
