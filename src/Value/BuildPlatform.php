<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Value;

/** Closed current Bedrock device/build-platform domain. Zero is intentionally undefined. */
enum BuildPlatform: int
{
    case Unknown = -1;
    case Google = 1;
    case Ios = 2;
    case Osx = 3;
    case Amazon = 4;
    case Win32 = 8;
    case Dedicated = 9;
    case Sony = 11;
    case Nintendo = 12;
    case Xbox = 13;
    case Linux = 15;
}
