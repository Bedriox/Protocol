<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

enum PlayStatus: int
{
    case LoginSuccess = 0;
    case LoginFailedClientOld = 1;
    case LoginFailedServerOld = 2;
    case PlayerSpawn = 3;
    case LoginFailedInvalidTenant = 4;
    case EducationToVanillaMismatch = 5;
    case VanillaToEducationMismatch = 6;
    case ServerFullSubClient = 7;
    case EditorToVanillaMismatch = 8;
    case VanillaToEditorMismatch = 9;
}
