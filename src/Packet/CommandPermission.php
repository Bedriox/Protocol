<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

enum CommandPermission: string
{
    case Any = 'any';
    case GameDirectors = 'gamedirectors';
    case Admin = 'admin';
    case Host = 'host';
    case Owner = 'owner';
    case Internal = 'internal';
}
