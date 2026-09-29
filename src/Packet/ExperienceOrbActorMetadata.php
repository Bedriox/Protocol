<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

use Bedriox\Protocol\Exception\InvalidValueException;

/** Complete current-protocol metadata for an experience-orb actor. */
final class ExperienceOrbActorMetadata
{
    public const string IDENTIFIER = 'minecraft:xp_orb';

    private const int EXPERIENCE_VALUE = 15;

    private function __construct() {}

    /** @return list<ActorMetadata> */
    public static function baseline(int $experienceValue): array
    {
        if ($experienceValue <= 0 || $experienceValue > 0x7fffffff) {
            throw new InvalidValueException('Experience-orb value must fit a positive signed integer.');
        }

        return [ActorMetadata::int(self::EXPERIENCE_VALUE, $experienceValue)];
    }
}
