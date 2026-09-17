<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

use Bedriox\Protocol\Exception\InvalidValueException;

final readonly class PlayerAttribute
{
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
        if ($minimum > $maximum || $value < $minimum || $value > $maximum) {
            throw new InvalidValueException('Attribute bounds or current value are inconsistent.');
        }
    }
}
