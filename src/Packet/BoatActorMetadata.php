<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

use Bedriox\Protocol\Exception\InvalidValueException;

/** Complete current-protocol presentation metadata for boats and chest boats. */
final class BoatActorMetadata
{
    private const int FLAGS = 0;
    private const int STRUCTURAL_INTEGRITY = 1;
    private const int VARIANT = 2;
    private const int HURT_TICKS = 11;
    private const int HURT_DIRECTION = 12;
    private const int ROW_TIME_LEFT = 13;
    private const int ROW_TIME_RIGHT = 14;
    private const int IS_BUOYANT = 118;
    private const int BUOYANCY_DATA = 119;
    private const string WATER_BUOYANCY_DATA = '{"apply_gravity":true,"base_buoyancy":1.0,"big_wave_probability":0.03,"big_wave_speed":10.0,"can_auto_step_from_liquid":false,"drag_down_on_buoyancy_removed":0.0,"liquid_blocks":["minecraft:water","minecraft:flowing_water"],"movement_type":"waves"}';

    private function __construct() {}

    /** @return list<ActorMetadata> */
    public static function baseline(
        int $variant,
        float $structuralIntegrity = 0.0,
        int $hurtTicks = 0,
        int $hurtDirection = 1,
        float $rowTimeLeft = 0.0,
        float $rowTimeRight = 0.0,
    ): array {
        if ($variant < 0 || $variant > 0x7fff) {
            throw new InvalidValueException('Boat variant is outside its supported range.');
        }
        if (!is_finite($structuralIntegrity) || $structuralIntegrity < 0.0 || $structuralIntegrity > 1_000_000.0) {
            throw new InvalidValueException('Boat structural integrity is outside its supported range.');
        }
        if ($hurtTicks < 0 || $hurtTicks > 0x7fff || ($hurtDirection !== -1 && $hurtDirection !== 1)) {
            throw new InvalidValueException('Boat hurt presentation is outside its supported range.');
        }
        foreach ([$rowTimeLeft, $rowTimeRight] as $rowTime) {
            if (!is_finite($rowTime) || $rowTime < 0.0 || $rowTime > 1_000_000.0) {
                throw new InvalidValueException('Boat paddle time is outside its supported range.');
            }
        }

        return [
            ActorMetadata::long(self::FLAGS, ActorFlag::combine(
                ActorFlag::HasCollision,
                ActorFlag::HasGravity,
            )),
            ActorMetadata::int(self::STRUCTURAL_INTEGRITY, (int) round($structuralIntegrity)),
            ActorMetadata::int(self::VARIANT, $variant),
            ActorMetadata::int(self::HURT_TICKS, $hurtTicks),
            ActorMetadata::int(self::HURT_DIRECTION, $hurtDirection),
            ActorMetadata::float(self::ROW_TIME_LEFT, $rowTimeLeft),
            ActorMetadata::float(self::ROW_TIME_RIGHT, $rowTimeRight),
            ActorMetadata::byte(self::IS_BUOYANT, 1),
            ActorMetadata::string(self::BUOYANCY_DATA, self::WATER_BUOYANCY_DATA),
        ];
    }
}
