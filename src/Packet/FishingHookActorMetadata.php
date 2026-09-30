<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

use Bedriox\Protocol\Exception\InvalidValueException;

/** Current-protocol fishing-hook metadata linked to its owning player actor. */
final class FishingHookActorMetadata
{
    private const int OWNER_RUNTIME_ENTITY_ID = 5;

    private function __construct() {}

    /** @return list<ActorMetadata> */
    public static function baseline(int $ownerRuntimeEntityId): array
    {
        if ($ownerRuntimeEntityId < 1 || $ownerRuntimeEntityId >= PHP_INT_MAX) {
            throw new InvalidValueException('Fishing-hook owner runtime entity ID is invalid.');
        }

        return [
            ActorMetadata::long(0, ActorFlag::HasGravity->mask()),
            ActorMetadata::long(self::OWNER_RUNTIME_ENTITY_ID, $ownerRuntimeEntityId),
        ];
    }
}
