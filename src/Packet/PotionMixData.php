<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

use Bedriox\Protocol\Exception\InvalidValueException;

/** One brewing-stand potion transformation advertised in crafting data. */
final readonly class PotionMixData
{
    public function __construct(
        public int $inputItemId,
        public int $inputAuxiliaryValue,
        public int $reagentItemId,
        public int $reagentAuxiliaryValue,
        public int $outputItemId,
        public int $outputAuxiliaryValue,
    ) {
        foreach ([
            $this->inputItemId,
            $this->inputAuxiliaryValue,
            $this->reagentItemId,
            $this->reagentAuxiliaryValue,
            $this->outputItemId,
            $this->outputAuxiliaryValue,
        ] as $value) {
            if ($value < -0x80000000 || $value > 0x7fffffff) {
                throw new InvalidValueException('Potion-mix fields must fit signed 32-bit integers.');
            }
        }
    }
}
