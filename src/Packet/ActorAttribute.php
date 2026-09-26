<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

use Bedriox\Protocol\Exception\InvalidValueException;

/** One current-protocol attribute update for any actor runtime ID. */
readonly class ActorAttribute
{
    private const int MAXIMUM_MODIFIERS = 64;

    public const string HEALTH = 'minecraft:health';
    public const string HUNGER = 'minecraft:player.hunger';
    public const string SATURATION = 'minecraft:player.saturation';

    final public function __construct(
        public string $name,
        public float $minimum,
        public float $maximum,
        public float $value,
        public float $defaultMinimum,
        public float $defaultMaximum,
        public float $default,
        /** @var list<ActorAttributeModifier> */
        public array $modifiers = [],
    ) {
        CodecSupport::validateString($name, 128, 'Attribute name');
        foreach ([$minimum, $maximum, $value, $defaultMinimum, $defaultMaximum, $default] as $number) {
            CodecSupport::validateFiniteFloat($number, 'Attribute value');
        }
        if ($minimum > $maximum || $value < $minimum || $value > $maximum
            || $defaultMinimum > $defaultMaximum || $default < $defaultMinimum || $default > $defaultMaximum) {
            throw new InvalidValueException('Attribute bounds or current value are inconsistent.');
        }
        CodecSupport::validateCount($modifiers, self::MAXIMUM_MODIFIERS, 'Actor attribute modifiers');
        foreach ($modifiers as $modifier) {
            if (!$modifier instanceof ActorAttributeModifier) {
                throw new InvalidValueException('Actor attribute modifiers must contain typed values.');
            }
        }
    }

    public static function health(float $value, float $maximum = 20.0): static
    {
        return new static(self::HEALTH, 0.0, $maximum, $value, 0.0, $maximum, $maximum);
    }

    public static function hunger(float $value): static
    {
        return new static(self::HUNGER, 0.0, 20.0, $value, 0.0, 20.0, 20.0);
    }

    public static function saturation(float $value): static
    {
        return new static(self::SATURATION, 0.0, 20.0, $value, 0.0, 20.0, 20.0);
    }
}
