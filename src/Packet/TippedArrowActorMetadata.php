<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

use Bedriox\Protocol\Exception\InvalidValueException;

/** Current-protocol metadata for an arrow carrying a potion trail. */
final class TippedArrowActorMetadata
{
    private function __construct() {}

    /** @return list<ActorMetadata> */
    public static function baseline(int $potionAuxiliaryValue): array
    {
        if ($potionAuxiliaryValue < 0 || $potionAuxiliaryValue > 0xff) {
            throw new InvalidValueException('Tipped-arrow potion auxiliary value must fit an unsigned byte.');
        }

        return [
            ActorMetadata::long(0, ActorFlag::HasGravity->mask()),
            ActorMetadata::byte(32, max(0, $potionAuxiliaryValue - 1)),
        ];
    }
}
