<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

enum PredictionType: int
{
    case Player = 0;
    case Vehicle = 1;
}
