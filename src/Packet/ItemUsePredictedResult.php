<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

enum ItemUsePredictedResult: int
{
    case Failure = 0;
    case Success = 1;
}
