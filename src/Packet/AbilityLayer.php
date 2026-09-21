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

    /**
     * @param list<Ability> $supported
     * @param list<Ability> $enabled
     */
    public static function fromAbilities(
        int $type,
        array $supported,
        array $enabled,
        float $flySpeed,
        float $verticalFlySpeed,
        float $walkSpeed,
    ): self {
        $supportedMask = self::maskOf(...$supported);
        $enabledMask = self::maskOf(...$enabled);
        if (($enabledMask & ~$supportedMask) !== 0) {
            throw new InvalidValueException('Enabled abilities must be present in the supported ability set.');
        }
        return new self($type, $supportedMask, $enabledMask, $flySpeed, $verticalFlySpeed, $walkSpeed);
    }

    public static function maskOf(Ability ...$abilities): int
    {
        $mask = 0;
        foreach ($abilities as $ability) {
            if (($mask & $ability->mask()) !== 0) {
                throw new InvalidValueException('Ability masks cannot contain duplicate abilities.');
            }
            $mask |= $ability->mask();
        }
        return $mask;
    }

    public function supports(Ability $ability): bool
    {
        return ($this->abilitiesSet & $ability->mask()) !== 0;
    }

    public function enabled(Ability $ability): bool
    {
        return ($this->abilityValues & $ability->mask()) !== 0;
    }
}
