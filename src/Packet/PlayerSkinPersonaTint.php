<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

use Bedriox\Protocol\Exception\InvalidValueException;

/** Immutable four-channel persona tint carried by a player skin. */
final readonly class PlayerSkinPersonaTint
{
    /** @var list<int> */
    public array $colors;

    /** @param array<int, int> $colors */
    public function __construct(public PlayerSkinPersonaPieceType $type, array $colors)
    {
        if (!array_is_list($colors) || count($colors) !== 4) {
            throw new InvalidValueException('Persona tint must contain exactly four colors.');
        }
        foreach ($colors as $color) {
            if ($color < -2_147_483_648 || $color > 2_147_483_647) {
                throw new InvalidValueException('Persona tint color is outside the signed 32-bit range.');
            }
        }
        $this->colors = $colors;
    }
}
