<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

/** Current Bedrock numeric effect identities for actor effect synchronization. */
enum MobEffectType: int
{
    case Speed = 1;
    case Slowness = 2;
    case Haste = 3;
    case MiningFatigue = 4;
    case Strength = 5;
    case InstantHealth = 6;
    case InstantDamage = 7;
    case JumpBoost = 8;
    case Nausea = 9;
    case Regeneration = 10;
    case Resistance = 11;
    case FireResistance = 12;
    case WaterBreathing = 13;
    case Invisibility = 14;
    case Blindness = 15;
    case NightVision = 16;
    case Hunger = 17;
    case Weakness = 18;
    case Poison = 19;
    case Wither = 20;
    case HealthBoost = 21;
    case Absorption = 22;
    case Saturation = 23;
    case Levitation = 24;
    case FatalPoison = 25;
    case ConduitPower = 26;
    case SlowFalling = 27;
    case BadOmen = 28;
    case VillageHero = 29;
    case Darkness = 30;
    case TrialOmen = 31;
    case WindCharged = 32;
    case Weaving = 33;
    case Oozing = 34;
    case Infested = 35;
}
