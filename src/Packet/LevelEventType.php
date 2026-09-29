<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

/** Named protocol-2193 level events used by authoritative block interaction. */
enum LevelEventType: int
{
    case PotionSplash = 2002;
    case MobSpawn = 2004;
    case DragonEggTeleport = 2010;
    case EndermanTeleport = 2013;
    case StartRain = 3001;
    case StartThunder = 3002;
    case StopRain = 3003;
    case StopThunder = 3004;
    case DestroyBlock = 2001;
    case CrackBlock = 2014;
    case DestroyBlockWithoutSound = 2021;
    case StartBlockBreak = 3600;
    case StopBlockBreak = 3601;
    case UpdateBlockBreak = 3602;
    case PunchBlockDown = 3603;
    case PunchBlockUp = 3604;
    case PunchBlockNorth = 3605;
    case PunchBlockSouth = 3606;
    case PunchBlockWest = 3607;
    case PunchBlockEast = 3608;
}
