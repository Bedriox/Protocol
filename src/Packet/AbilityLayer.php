<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

use Bedriox\Protocol\Exception\InvalidValueException;

final readonly class AbilityLayer
{
    public function __construct(
        public int $type,
        public int $abilitiesSet,
        public int $abilityValues,
        public float $flySpeed,
        public float $verticalFlySpeed,
        public float $walkSpeed,
    )
    {
        if ($type < 0 || $type > 5 || $abilitiesSet < -0x80000000 || $abilitiesSet > 0x7fffffff
            || $abilityValues < -0x80000000 || $abilityValues > 0x7fffffff) {
            throw new InvalidValueException('Ability-layer enum or masks are outside their wire range.');
        }
        CodecSupport::validateFiniteFloat($flySpeed, 'Fly speed');
        CodecSupport::validateFiniteFloat($verticalFlySpeed, 'Vertical fly speed');
        CodecSupport::validateFiniteFloat($walkSpeed, 'Walk speed');
    }
}
