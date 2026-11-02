<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

enum AudioContentPlaybackType: string
{
    case Music = 'Music';
    case Sound = 'Sound';
}
