<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

/** Vanilla dimension identifiers carried by current Bedrock packets. */
enum DimensionId: int
{
    case Overworld = 0;
    case Nether = 1;
    case End = 2;
}
