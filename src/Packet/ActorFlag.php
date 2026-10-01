<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

/** Current Bedrock actor-flag bit indexes used by Bedriox actor baselines. */
enum ActorFlag: int
{
    case OnFire = 0;
    case Sneaking = 1;
    case Sprinting = 3;
    case UsingItem = 4;
    case Invisible = 5;
    case Saddled = 8;
    case Baby = 11;
    case CanShowName = 14;
    case NoAi = 16;
    case CanClimb = 19;
    case Sheared = 31;
    case Breathing = 35;
    case Linger = 46;
    case HasCollision = 48;
    case HasGravity = 49;

    public function mask(): int
    {
        return 1 << $this->value;
    }

    public static function combine(self ...$flags): int
    {
        $mask = 0;
        foreach ($flags as $flag) {
            $mask |= $flag->mask();
        }
        return $mask;
    }
}
