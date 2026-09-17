<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

enum ItemUseTriggerType: int
{
    case Unknown = 0;
    case PlayerInput = 1;
    case SimulationTick = 2;
}
