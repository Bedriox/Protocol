<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

enum CommandOriginType: int
{
    case Player = 0;
    case Block = 1;
    case MinecartBlock = 2;
    case DevConsole = 3;
    case Test = 4;
    case AutomationPlayer = 5;
    case ClientAutomation = 6;
    case DedicatedServer = 7;
    case Entity = 8;
    case Virtual = 9;
    case GameArgument = 10;
    case EntityServer = 11;
    case Precompiled = 12;
    case GameDirectorEntityServer = 13;
    case Script = 14;
    case ExecuteContext = 15;
}
