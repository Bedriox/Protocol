<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

enum ItemUseClientCooldownState: int
{
    case Off = 0;
    case On = 1;
}
