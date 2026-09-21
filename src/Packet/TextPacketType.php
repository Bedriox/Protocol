<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

/** Current Bedrock text-type discriminators. */
enum TextPacketType: int
{
    case Raw = 0;
    case Chat = 1;
    case Translation = 2;
    case Popup = 3;
    case JukeboxPopup = 4;
    case Tip = 5;
    case System = 6;
    case Whisper = 7;
    case Announcement = 8;
    case WhisperJson = 9;
    case Json = 10;
    case AnnouncementJson = 11;
}
