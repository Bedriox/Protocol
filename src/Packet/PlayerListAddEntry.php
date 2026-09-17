<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

use Bedriox\Protocol\Exception\InvalidValueException;
use Bedriox\Protocol\Value\BuildPlatform;

final readonly class PlayerListAddEntry
{
    public function __construct(
        public string $uuid,
        public int $uniqueEntityId,
        public string $name,
        public string $xuid,
        public string $platformChatId,
        public BuildPlatform $buildPlatform,
        public PlayerSkin $skin,
        public bool $teacher = false,
        public bool $host = false,
        public bool $subClient = false,
        public int $colorArgb = 0,
        public bool $trustedSkin = false,
    ) {
        CodecSupport::uuidToWire($uuid);
        CodecSupport::validateString($name, CodecSupport::MAX_PLAYER_NAME_BYTES, 'Player-list name');
        CodecSupport::validateString($xuid, CodecSupport::MAX_SHORT_STRING_BYTES, 'Player-list XUID');
        CodecSupport::validateString($platformChatId, CodecSupport::MAX_SHORT_STRING_BYTES, 'Platform chat ID');
        if ($colorArgb < 0 || $colorArgb > 0xffffffff) {
            throw new InvalidValueException('Player-list color value is outside its wire range.');
        }
    }
}
