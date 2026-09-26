<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

enum ActorEventType: int
{
    case None = 0;
    case Jump = 1;
    case Hurt = 2;
    case Death = 3;
    case AttackStart = 4;
    case AttackStop = 5;
    case TameFailed = 6;
    case TameSucceeded = 7;
    case ShakeWetness = 8;
    case UseItem = 9;
    case EatGrass = 10;
    case FishHookBubble = 11;
    case FishHookPosition = 12;
    case FishHookTime = 13;
    case FishHookTease = 14;
    case SquidFleeing = 15;
    case ZombieVillagerCure = 16;
    case PlayAmbient = 17;
    case Respawn = 18;
    case GolemFlowerOffer = 19;
    case GolemFlowerWithdraw = 20;
    case LoveParticles = 21;
    case VillagerAngry = 22;
    case VillagerHappy = 23;
    case WitchHatMagic = 24;
    case FireworkExplode = 25;
    case InLoveHearts = 26;
    case SilverfishMergeWithStone = 27;
    case GuardianAttackAnimation = 28;
    case WitchDrinkPotion = 29;
    case WitchThrowPotion = 30;
    case PrimeTntMinecart = 31;
    case PrimeCreeper = 32;
    case AirSupply = 33;
    case PlayerAddXpLevels = 34;
    case ElderGuardianCurse = 35;
    case AgentArmSwing = 36;
    case EnderDragonDeath = 37;
    case DustParticles = 38;
    case ArrowShake = 39;
    case EatingItem = 57;
    case BabyAnimalFeed = 60;
    case DeathSmokeCloud = 61;
    case CompleteTrade = 62;
    case RemoveLeash = 63;
    case Caravan = 64;
    case ConsumeTotem = 65;
    case CheckTreasureHunterAchievement = 66;
    case EntitySpawn = 67;
    case DragonFlaming = 68;
    case UpdateItemStackSize = 69;
    case StartSwimming = 70;
    case BalloonPop = 71;
    case TreasureHunt = 72;
    case SummonAgent = 73;
    case FinishedChargingItem = 74;
    case LandedOnGround = 75;
    case EntityGrowUp = 76;
    case VibrationDetected = 77;
    case DrinkMilk = 78;
    case ShakeWetnessStop = 79;
    case KineticDamageDealt = 80;
    case HurtWithoutReceivingDamage = 81;
}
