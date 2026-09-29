<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

use Bedriox\Protocol\Exception\InvalidValueException;

/** Complete server-controlled metadata for a current-protocol area-effect cloud actor. */
final class AreaEffectCloudActorMetadata
{
    private function __construct() {}

    /** @return list<ActorMetadata> */
    public static function baseline(float $radius, int $argbColor = 0): array
    {
        if (!is_finite($radius) || $radius < 0.0 || $radius > 32.0) {
            throw new InvalidValueException('Area-effect cloud radius is invalid.');
        }

        return [
            ActorMetadata::long(0, 0),
            ActorMetadata::int(8, $argbColor),
            ActorMetadata::byte(9, 0),
            ActorMetadata::float(60, $radius),
            ActorMetadata::int(61, 0),
            ActorMetadata::int(94, -1),
            ActorMetadata::int(95, 0),
            ActorMetadata::float(96, 0.0),
            ActorMetadata::float(97, 0.0),
            ActorMetadata::int(98, 0),
        ];
    }
}
