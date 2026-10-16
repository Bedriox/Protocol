<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

final readonly class ScoreboardIdentityEntry
{
    public function __construct(
        public int $scoreboardId,
        public ?int $playerUniqueId = null,
    ) {
    }
}
