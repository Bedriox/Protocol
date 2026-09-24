<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

/** Current Bedrock actor-flag bit indexes used by Bedriox's player baseline. */
enum ActorFlag: int
{
    case Sneaking = 1;
    case Sprinting = 3;
    case UsingItem = 4;
    case CanShowName = 14;
    case CanClimb = 19;
    case Breathing = 35;
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
