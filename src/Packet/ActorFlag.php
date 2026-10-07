<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

/** Current Bedrock actor-flag bit indexes used by Bedriox actor baselines. */
enum ActorFlag: int
{
    case OnFire = 0;
    case Sneaking = 1;
    case Riding = 2;
    case Sprinting = 3;
    case UsingItem = 4;
    case Invisible = 5;
    case Tempted = 6;
    case InLove = 7;
    case Saddled = 8;
    case Powered = 9;
    case Ignited = 10;
    case Baby = 11;
    case CanShowName = 14;
    case NoAi = 16;
    case WallClimbing = 18;
    case CanClimb = 19;
    case Sitting = 24;
    case Angry = 25;
    case Charged = 27;
    case Tamed = 28;
    case Leashed = 30;
    case Sheared = 31;
    case Breathing = 35;
    case Chested = 36;
    case ShowBottom = 38;
    case Standing = 39;
    case Shaking = 40;
    case WasdControlled = 44;
    case CanPowerJump = 45;
    case Linger = 46;
    case HasCollision = 48;
    case HasGravity = 49;
    case FireImmune = 50;
    case Eating = 62;
    case LayingDown = 63;
    case Sneezing = 64;
    case Trusting = 65;
    case Rolling = 66;
    case Scared = 67;
    case Sleeping = 75;
    case RamAttack = 96;
    case Sniffing = 104;
    case Digging = 105;

    public function mask(): int
    {
        return 1 << ($this->value % 64);
    }

    public function word(): int
    {
        return intdiv($this->value, 64);
    }

    public static function combine(self ...$flags): int
    {
        $mask = 0;
        foreach ($flags as $flag) {
            if ($flag->word() !== 0) {
                continue;
            }
            $mask |= $flag->mask();
        }
        return $mask;
    }

    public static function combineSecondWord(self ...$flags): int
    {
        $mask = 0;
        foreach ($flags as $flag) {
            if ($flag->word() === 1) {
                $mask |= $flag->mask();
            }
        }

        return $mask;
    }
}
