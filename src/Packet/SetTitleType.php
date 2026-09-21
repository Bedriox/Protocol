<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

/** Current SetTitle operation discriminators. */
enum SetTitleType: int
{
    case Clear = 0;
    case Reset = 1;
    case Title = 2;
    case Subtitle = 3;
    case ActionBar = 4;
    case Times = 5;
    case TitleJson = 6;
    case SubtitleJson = 7;
    case ActionBarJson = 8;
}
