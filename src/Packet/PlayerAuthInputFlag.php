<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

/** Current PlayerAuthInput input-data indexes as transmitted by Bedrock. */
enum PlayerAuthInputFlag: int
{
    case Ascend = 0;
    case Descend = 1;
    case NorthJump = 2;
    case JumpDown = 3;
    case SprintDown = 4;
    case ChangeHeight = 5;
    case Jumping = 6;
    case AutoJumpingInWater = 7;
    case Sneaking = 8;
    case SneakDown = 9;
    case Up = 10;
    case Down = 11;
    case Left = 12;
    case Right = 13;
    case UpLeft = 14;
    case UpRight = 15;
    case WantUp = 16;
    case WantDown = 17;
    case WantDownSlow = 18;
    case WantUpSlow = 19;
    case Sprinting = 20;
    case AscendBlock = 21;
    case DescendBlock = 22;
    case SneakToggleDown = 23;
    case PersistSneak = 24;
    case StartSprinting = 25;
    case StopSprinting = 26;
    case StartSneaking = 27;
    case StopSneaking = 28;
    case StartSwimming = 29;
    case StopSwimming = 30;
    case StartJumping = 31;
    case StartGliding = 32;
    case StopGliding = 33;
    case PerformItemInteraction = 34;
    case PerformBlockActions = 35;
    case PerformItemStackRequest = 36;
    case HandledTeleport = 37;
    case Emoting = 38;
    case MissedSwing = 39;
    case StartCrawling = 40;
    case StopCrawling = 41;
    case StartFlying = 42;
    case StopFlying = 43;
    case ClientAckServerData = 44;
    case IsInClientPredictedVehicle = 45;
    case PaddlingLeft = 46;
    case PaddlingRight = 47;
    case BlockBreakingDelayEnabled = 48;
    case HorizontalCollision = 49;
    case VerticalCollision = 50;
    case DownLeft = 51;
    case DownRight = 52;
    case StartUsingItem = 53;
    case CameraRelativeMovementEnabled = 54;
    case RotationControlledByMoveDirection = 55;
    case StartSpinAttack = 56;
    case StopSpinAttack = 57;
    case IsHotbarOnlyTouch = 58;
    case JumpReleasedRaw = 59;
    case JumpPressedRaw = 60;
    case JumpCurrentRaw = 61;
    case SneakReleasedRaw = 62;
    case SneakPressedRaw = 63;
    case SneakCurrentRaw = 64;
    case InternalUpdate = 65;

    public const int COUNT = 66;
}
