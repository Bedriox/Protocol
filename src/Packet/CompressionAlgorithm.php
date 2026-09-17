<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

enum CompressionAlgorithm: int
{
    case Zlib = 0;
    case Snappy = 1;
    case None = 2;
}
