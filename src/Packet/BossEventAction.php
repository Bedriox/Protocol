<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

/** Operations in the fixed protocol-2193 boss-event payload. */
enum BossEventAction: int
{
    case CREATE = 0;
    case REGISTER_PLAYER = 1;
    case REMOVE = 2;
    case UNREGISTER_PLAYER = 3;
    case UPDATE_PERCENTAGE = 4;
    case UPDATE_NAME = 5;
    case UPDATE_PROPERTIES = 6;
    case UPDATE_STYLE = 7;
    case QUERY = 8;
}
