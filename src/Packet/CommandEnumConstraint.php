<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

/** A current-protocol semantic constraint attached to one hard-enum value. */
enum CommandEnumConstraint: int
{
    case CheatsEnabled = 0;
    case OperatorPermissions = 1;
    case HostPermissions = 2;
    case AllowAliases = 3;
}
