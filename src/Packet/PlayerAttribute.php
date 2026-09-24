<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

use Bedriox\Protocol\Exception\InvalidValueException;

final readonly class PlayerAttribute
{
    public const string HEALTH = 'minecraft:health';
    public const string HUNGER = 'minecraft:player.hunger';
    public const string SATURATION = 'minecraft:player.saturation';

    public function __construct(
        public string $name,
        public float $minimum,
        public float $maximum,
        public float $value,
        public float $defaultMinimum,
        public float $defaultMaximum,
        public float $default,
    ) {
        CodecSupport::validateString($name, 128, 'Attribute name');
        foreach ([$minimum, $maximum, $value, $defaultMinimum, $defaultMaximum, $default] as $number) {
            CodecSupport::validateFiniteFloat($number, 'Attribute value');
        }
        if ($minimum > $maximum || $value < $minimum || $value > $maximum
            || $defaultMinimum > $defaultMaximum || $default < $defaultMinimum || $default > $defaultMaximum) {
            throw new InvalidValueException('Attribute bounds or current value are inconsistent.');
        }
    }

    public static function health(float $value, float $maximum = 20.0): self
    {
        return new self(self::HEALTH, 0.0, $maximum, $value, 0.0, $maximum, $maximum);
    }

    public static function hunger(float $value): self
    {
        return new self(self::HUNGER, 0.0, 20.0, $value, 0.0, 20.0, 20.0);
    }

    public static function saturation(float $value): self
    {
        return new self(self::SATURATION, 0.0, 20.0, $value, 0.0, 20.0, 20.0);
    }
}
