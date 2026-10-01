<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

/** Canonical current-protocol player actor metadata snapshots. */
final class PlayerActorMetadata
{
    private const int FLAGS = 0;
    private const int AIR_SUPPLY = 7;
    private const int MAXIMUM_AIR_SUPPLY = 42;

    private function __construct()
    {
    }

    /** @return list<ActorMetadata> */
    public static function baseline(
        string $name,
        bool $sneaking = false,
        bool $sprinting = false,
        bool $usingItem = false,
        bool $riding = false,
    ): array
    {
        $width = self::float32(0.6);
        $height = self::float32(1.8);

        return [
            self::flagsEntry(self::flags($sneaking, $sprinting, $usingItem, $riding)),
            ActorMetadata::int(1, 20),
            ActorMetadata::byte(3, 0),
            ActorMetadata::string(4, $name),
            self::airSupply(400),
            ActorMetadata::long(37, -1),
            ActorMetadata::float(38, 1.0),
            self::maximumAirSupply(400),
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
        bool $riding = false,
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
        if ($riding) {
            $flags |= ActorFlag::Riding->mask();
        }
        return $flags;
    }

    public static function flagsEntry(int $flags): ActorMetadata
    {
        return ActorMetadata::long(self::FLAGS, $flags);
    }

    public static function airSupply(int $ticks): ActorMetadata
    {
        return ActorMetadata::short(self::AIR_SUPPLY, $ticks);
    }

    public static function maximumAirSupply(int $ticks): ActorMetadata
    {
        return ActorMetadata::short(self::MAXIMUM_AIR_SUPPLY, $ticks);
    }

    public static function isFlags(ActorMetadata $metadata): bool
    {
        return $metadata->id === self::FLAGS;
    }

    public static function isAirSupply(ActorMetadata $metadata): bool
    {
        return $metadata->id === self::AIR_SUPPLY;
    }

    public static function isMaximumAirSupply(ActorMetadata $metadata): bool
    {
        return $metadata->id === self::MAXIMUM_AIR_SUPPLY;
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
