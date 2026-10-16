<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

/** Per-entry operation and scorer type used by SetScore in protocol 2193. */
enum ScoreboardEntryType: int
{
    case REMOVE = 0;
    case PLAYER = 1;
    case ENTITY = 2;
    case FAKE_PLAYER = 3;

    public function wireName(): string
    {
        return match ($this) {
            self::REMOVE => 'remove',
            self::PLAYER => 'changeplayer',
            self::ENTITY => 'changeentity',
            self::FAKE_PLAYER => 'changefakeplayer',
        };
    }
}
