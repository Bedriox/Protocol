<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

/** Protocol-2193 ability ordinals and mask bits. */
enum Ability: int
{
    case Build = 0;
    case Mine = 1;
    case DoorsAndSwitches = 2;
    case OpenContainers = 3;
    case AttackPlayers = 4;
    case AttackMobs = 5;
    case OperatorCommands = 6;
    case Teleport = 7;
    case Invulnerable = 8;
    case Flying = 9;
    case MayFly = 10;
    case InstantBuild = 11;
    case Lightning = 12;
    case FlySpeed = 13;
    case WalkSpeed = 14;
    case Muted = 15;
    case WorldBuilder = 16;
    case NoClip = 17;
    case PrivilegedBuilder = 18;
    case VerticalFlySpeed = 19;

    public function mask(): int
    {
        return 1 << $this->value;
    }
}
