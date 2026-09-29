<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

use Bedriox\Protocol\Exception\InvalidValueException;

final readonly class LevelEventParticleColor
{
    public function __construct(
        public int $red,
        public int $green,
        public int $blue,
        public int $alpha = 255,
    ) {
        foreach ([$red, $green, $blue, $alpha] as $channel) {
            if ($channel < 0 || $channel > 255) {
                throw new InvalidValueException('Particle color channels must be between 0 and 255.');
            }
        }
    }

    public function signedArgb(): int
    {
        $value = ($this->alpha << 24) | ($this->red << 16) | ($this->green << 8) | $this->blue;

        return $value > 0x7fffffff ? $value - 0x100000000 : $value;
    }
}
