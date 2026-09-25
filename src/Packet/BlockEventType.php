<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

/** Block-specific state transition carried by the current block-event packet. */
enum BlockEventType: int
{
    case ChangeState = 1;
}
