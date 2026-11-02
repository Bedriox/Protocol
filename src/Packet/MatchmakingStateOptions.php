<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

use Bedriox\Protocol\Exception\InvalidValueException;

final readonly class MatchmakingStateOptions
{
    public function __construct(
        public ?string $triggeringPlayerName,
        public ?bool $triggeredByLocalPlayer,
    ) {
        if ($triggeringPlayerName !== null) {
            CodecSupport::validateString($triggeringPlayerName, CodecSupport::MAX_PLAYER_NAME_BYTES, 'Matchmaking triggering player name');
        }
    }
}
