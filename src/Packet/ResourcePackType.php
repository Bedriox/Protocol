<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

enum ResourcePackType: int
{
    case Invalid = 0;
    case Addon = 1;
    case Cached = 2;
    case CopyProtected = 3;
    case DataAddon = 4;
    case PersonaPiece = 5;
    case Resources = 6;
    case Skins = 7;
    case WorldTemplate = 8;
}
