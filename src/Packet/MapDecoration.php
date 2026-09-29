<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

use Bedriox\Protocol\Exception\InvalidValueException;

final readonly class MapDecoration
{
    public function __construct(
        public int $image,
        public int $rotation,
        public int $xOffset,
        public int $yOffset,
        public string $label,
        public int $color,
    ) {
        if ($image < -0x80 || $image > 0x7f || $rotation < 0 || $rotation > 0xff
            || $xOffset < 0 || $xOffset > 0xff || $yOffset < 0 || $yOffset > 0xff
            || $color < -0x80000000 || $color > 0x7fffffff) {
            throw new InvalidValueException('Map decoration contains an out-of-range scalar.');
        }
        CodecSupport::validateString($label, CodecSupport::MAX_SHORT_STRING_BYTES, 'Map decoration label');
    }
}
