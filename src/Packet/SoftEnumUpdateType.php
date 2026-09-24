<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

enum SoftEnumUpdateType: int
{
    case Add = 0;
    case Remove = 1;
    case Replace = 2;
}
