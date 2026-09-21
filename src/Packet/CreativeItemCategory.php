<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

enum CreativeItemCategory: int
{
    case All = 0;
    case Construction = 1;
    case Nature = 2;
    case Equipment = 3;
    case Items = 4;
    case CommandOnly = 5;
    case Undefined = 6;
}
