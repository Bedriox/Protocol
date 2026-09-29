<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

use Bedriox\Protocol\Exception\InvalidValueException;

/** Complete current-protocol metadata for splash and lingering potion actors. */
final class PotionProjectileActorMetadata
{
    private const int POTION_AUXILIARY_VALUE = 36;

    private function __construct() {}

    /** @return list<ActorMetadata> */
    public static function baseline(int $potionAuxiliaryValue, bool $lingering): array
    {
        if ($potionAuxiliaryValue < 0 || $potionAuxiliaryValue > 0x7fff) {
            throw new InvalidValueException('Potion auxiliary value must fit a non-negative short.');
        }
        $flags = ActorFlag::HasGravity->mask();
        if ($lingering) {
            $flags |= ActorFlag::Linger->mask();
        }

        return [
            ActorMetadata::long(0, $flags),
            ActorMetadata::short(self::POTION_AUXILIARY_VALUE, $potionAuxiliaryValue),
        ];
    }
}
