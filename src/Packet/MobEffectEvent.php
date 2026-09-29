<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

/** Current Bedrock effect synchronization operation. */
enum MobEffectEvent: int
{
    case None = 0;
    case Add = 1;
    case Modify = 2;
    case Remove = 3;
}
