<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

/** Current protocol-2193 MoveActorAbsolute header bits. */
enum MoveActorAbsoluteFlag: int
{
    case OnGround = 0x01;
    case Teleported = 0x02;
    case ForceMove = 0x04;
    case ForceCompletion = 0x08;
}
