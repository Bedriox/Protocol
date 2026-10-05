<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

enum PlayerActionType: int
{
    case Unknown = -1;
    case StartDestroyBlock = 0;
    case AbortDestroyBlock = 1;
    case StopDestroyBlock = 2;
    case StartSleeping = 5;
    case StopSleeping = 6;
    case Respawn = 7;
    case StartJump = 8;
    case StartSprinting = 9;
    case StopSprinting = 10;
    case StartSneaking = 11;
    case StopSneaking = 12;
    case CreativeDestroyBlock = 13;
    case DimensionChangeSuccess = 14;
    case StartGliding = 15;
    case StopGliding = 16;
    case DenyDestroyBlock = 17;
    case CrackBlock = 18;
    case StartSwimming = 21;
    case StopSwimming = 22;
    case StartSpinAttack = 23;
    case StopSpinAttack = 24;
    case PredictDestroyBlock = 26;
    case ContinueDestroyBlock = 27;
    case StartItemUseOn = 28;
    case StopItemUseOn = 29;
    case HandledTeleport = 30;
    case MissedSwing = 31;
    case StartCrawling = 32;
    case StopCrawling = 33;
    case StartFlying = 34;
    case StopFlying = 35;
    case StartUsingItem = 37;
    case InternalUpdate = 38;
}
