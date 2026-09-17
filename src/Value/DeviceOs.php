<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Value;

/** Authenticated client-data DeviceOS domain; retained privately and not rebroadcast to peers. */
enum DeviceOs: int
{
    case Unknown = -1;
    case Android = 1;
    case Ios = 2;
    case Osx = 3;
    case Amazon = 4;
    case GearVr = 5;
    case Hololens = 6;
    case Windows = 7;
    case Win32 = 8;
    case Dedicated = 9;
    case TvOs = 10;
    case Playstation = 11;
    case Nintendo = 12;
    case Xbox = 13;
    case WindowsPhone = 14;
    case Linux = 15;
}
