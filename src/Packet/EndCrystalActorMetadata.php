<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

/** Current-protocol End Crystal beam presentation metadata. */
final class EndCrystalActorMetadata
{
    public const int BLOCK_TARGET = 47;

    private function __construct()
    {
    }

    public static function beamTarget(BlockPosition $target): ActorMetadata
    {
        return ActorMetadata::blockPosition(self::BLOCK_TARGET, $target);
    }
}
