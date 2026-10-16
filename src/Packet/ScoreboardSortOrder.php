<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

enum ScoreboardSortOrder: int
{
    case ASCENDING = 0;
    case DESCENDING = 1;
}
