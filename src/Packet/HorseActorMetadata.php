<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

/** Typed current-protocol metadata used by horse jump presentation. */
final class HorseActorMetadata
{
    private const int JUMP_DURATION = 10;

    public static function jumpDuration(int $duration): ActorMetadata
    {
        return ActorMetadata::byte(self::JUMP_DURATION, $duration);
    }
}
