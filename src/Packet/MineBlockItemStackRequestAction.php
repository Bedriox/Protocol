<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

use Bedriox\Protocol\Exception\InvalidValueException;

final readonly class MineBlockItemStackRequestAction implements ItemStackRequestAction
{
    public const int TYPE_ID = 9;

    public function __construct(
        public int $hotbarSlot,
        public int $predictedDurability,
        public int $stackNetworkId,
    ) {
        foreach ([$hotbarSlot, $predictedDurability, $stackNetworkId] as $value) {
            if ($value < -0x80000000 || $value > 0x7fffffff) {
                throw new InvalidValueException('Mine-block action scalar must fit a signed 32-bit integer.');
            }
        }
    }

    public function typeId(): int { return self::TYPE_ID; }
}
