<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

enum AuthenticationType: int
{
    case Full = 0;
    case Guest = 1;
    case SelfSigned = 2;
}
