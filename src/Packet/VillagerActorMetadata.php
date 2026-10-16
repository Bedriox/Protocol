<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

/** Current protocol-2193 villager presentation and trade-state metadata identifiers. */
final class VillagerActorMetadata
{
    public const int VARIANT = 2;
    public const int MARK_VARIANT = 43;
    public const int TRADE_TARGET_UNIQUE_ID = 67;
    public const int TRADE_TIER = 100;
    public const int MAXIMUM_TRADE_TIER = 101;
    public const int TRADE_EXPERIENCE = 102;
    public const int LOW_TIER_CURED_TRADE_DISCOUNT = 113;
    public const int HIGH_TIER_CURED_TRADE_DISCOUNT = 114;
    public const int NEARBY_CURED_TRADE_DISCOUNT = 115;
    public const int NEARBY_CURED_DISCOUNT_TIMESTAMP = 116;

    private function __construct()
    {
    }

    /** @return list<ActorMetadata> */
    public static function tradeState(
        int $variant,
        int $markVariant,
        int $tradeTargetUniqueId,
        int $tradeTier,
        int $maximumTradeTier,
        int $tradeExperience,
        int $lowTierCuredDiscount = 0,
        int $highTierCuredDiscount = 0,
        int $nearbyCuredDiscount = 0,
        int $nearbyCuredDiscountTimestamp = 0,
    ): array {
        return [
            ActorMetadata::int(self::VARIANT, $variant),
            ActorMetadata::int(self::MARK_VARIANT, $markVariant),
            ActorMetadata::long(self::TRADE_TARGET_UNIQUE_ID, $tradeTargetUniqueId),
            ActorMetadata::int(self::TRADE_TIER, $tradeTier),
            ActorMetadata::int(self::MAXIMUM_TRADE_TIER, $maximumTradeTier),
            ActorMetadata::int(self::TRADE_EXPERIENCE, $tradeExperience),
            ActorMetadata::int(self::LOW_TIER_CURED_TRADE_DISCOUNT, $lowTierCuredDiscount),
            ActorMetadata::int(self::HIGH_TIER_CURED_TRADE_DISCOUNT, $highTierCuredDiscount),
            ActorMetadata::int(self::NEARBY_CURED_TRADE_DISCOUNT, $nearbyCuredDiscount),
            ActorMetadata::int(self::NEARBY_CURED_DISCOUNT_TIMESTAMP, $nearbyCuredDiscountTimestamp),
        ];
    }

    public static function tradeTarget(int $uniqueEntityId): ActorMetadata
    {
        return ActorMetadata::long(self::TRADE_TARGET_UNIQUE_ID, $uniqueEntityId);
    }

    public static function tradeTier(int $tier): ActorMetadata
    {
        return ActorMetadata::int(self::TRADE_TIER, $tier);
    }

    public static function tradeExperience(int $experience): ActorMetadata
    {
        return ActorMetadata::int(self::TRADE_EXPERIENCE, $experience);
    }
}
