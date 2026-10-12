<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

use Bedriox\Protocol\Exception\InvalidValueException;

/** Complete current-protocol presentation metadata for ordinary and chest minecarts. */
final class MinecartActorMetadata
{
    public const string MINECART_IDENTIFIER = 'minecraft:minecart';
    public const string CHEST_MINECART_IDENTIFIER = 'minecraft:chest_minecart';

    private const int FLAGS = 0;
    private const int STRUCTURAL_DAMAGE = 1;
    private const int HURT_TICKS = 11;
    private const int HURT_DIRECTION = 12;
    private const int DISPLAY_BLOCK_STATE = 16;
    private const int DISPLAY_OFFSET = 17;
    private const int CUSTOM_DISPLAY = 18;

    private function __construct() {}

    /** @return list<ActorMetadata> */
    public static function baseline(
        float $structuralDamage = 0.0,
        int $hurtTicks = 0,
        int $hurtDirection = 1,
        ?int $displayBlockRuntimeId = null,
    ): array {
        if (!is_finite($structuralDamage) || $structuralDamage < 0.0 || $structuralDamage > 1_000_000.0) {
            throw new InvalidValueException('Minecart structural damage is outside its supported range.');
        }
        if ($hurtTicks < 0 || $hurtTicks > 0x7fff || ($hurtDirection !== -1 && $hurtDirection !== 1)) {
            throw new InvalidValueException('Minecart hurt presentation is outside its supported range.');
        }

        $metadata = [
            ActorMetadata::long(self::FLAGS, ActorFlag::combine(ActorFlag::HasCollision)),
            ActorMetadata::int(self::STRUCTURAL_DAMAGE, (int) round($structuralDamage)),
            ActorMetadata::int(self::HURT_TICKS, $hurtTicks),
            ActorMetadata::int(self::HURT_DIRECTION, $hurtDirection),
        ];
        if ($displayBlockRuntimeId !== null) {
            $metadata[] = ActorMetadata::int(self::DISPLAY_BLOCK_STATE, $displayBlockRuntimeId);
        }
        $metadata[] = ActorMetadata::int(self::DISPLAY_OFFSET, 6);
        $metadata[] = ActorMetadata::byte(self::CUSTOM_DISPLAY, $displayBlockRuntimeId === null ? 0 : 1);

        return $metadata;
    }
}
