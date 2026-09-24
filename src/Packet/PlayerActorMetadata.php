<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

/** Canonical current-protocol player actor metadata snapshots. */
final class PlayerActorMetadata
{
    private function __construct()
    {
    }

    /** @return list<ActorMetadata> */
    public static function baseline(
        string $name,
        bool $sneaking = false,
        bool $sprinting = false,
        bool $usingItem = false,
    ): array
    {
        $width = self::float32(0.6);
        $height = self::float32(1.8);

        return [
            ActorMetadata::long(0, self::flags($sneaking, $sprinting, $usingItem)),
            ActorMetadata::int(1, 20),
            ActorMetadata::byte(3, 0),
            ActorMetadata::string(4, $name),
            ActorMetadata::short(7, 400),
            ActorMetadata::long(37, -1),
            ActorMetadata::float(38, 1.0),
            ActorMetadata::short(42, 400),
            ActorMetadata::float(53, $width),
            ActorMetadata::float(54, $height),
            ActorMetadata::byte(81, 1),
            ActorMetadata::long(92, 0),
            ActorMetadata::float(120, 0.0),
            ActorMetadata::vector3(130, $width, $height, $width),
        ];
    }

    public static function flags(
        bool $sneaking = false,
        bool $sprinting = false,
        bool $usingItem = false,
    ): int
    {
        $flags = ActorFlag::combine(
            ActorFlag::CanShowName,
            ActorFlag::CanClimb,
            ActorFlag::Breathing,
            ActorFlag::HasCollision,
            ActorFlag::HasGravity,
        );
        if ($sneaking) {
            $flags |= ActorFlag::Sneaking->mask();
        }
        if ($sprinting) {
            $flags |= ActorFlag::Sprinting->mask();
        }
        if ($usingItem) {
            $flags |= ActorFlag::UsingItem->mask();
        }
        return $flags;
    }

    private static function float32(float $value): float
    {
        $decoded = unpack('gvalue', pack('g', $value));
        if ($decoded === false || !is_float($decoded['value'])) {
            throw new \LogicException('Unable to normalize player actor metadata float.');
        }
        return $decoded['value'];
    }
}
