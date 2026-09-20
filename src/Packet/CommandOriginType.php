<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

enum CommandOriginType: string
{
    case Player = 'player';
    case Block = 'commandblock';
    case MinecartBlock = 'minecartcommandblock';
    case DevConsole = 'devconsole';
    case Test = 'test';
    case AutomationPlayer = 'automationplayer';
    case ClientAutomation = 'clientautomation';
    case DedicatedServer = 'dedicatedserver';
    case Entity = 'entity';
    case Virtual = 'virtual';
    case GameArgument = 'gameargument';
    case EntityServer = 'entityserver';
    case Precompiled = 'precompiled';
    case GameDirectorEntityServer = 'gamedirectorentityserver';
    case Script = 'scripting';
    case ExecuteContext = 'executecontext';
}
