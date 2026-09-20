<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

final class MovePlayerMode
{
    public const int NORMAL = 0;
    public const int RESPAWN = 1;
    public const int TELEPORT = 2;
    public const int HEAD_ROTATION = 3;
}
