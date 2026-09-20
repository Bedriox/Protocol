<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

enum CommandPermissionLevel: int
{
    case Normal = 0;
    case Operator = 1;
    case Automation = 2;
    case Host = 3;
    case Owner = 4;
    case Internal = 5;
}
