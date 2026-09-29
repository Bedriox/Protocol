<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

use Bedriox\Protocol\Exception\InvalidValueException;

/** One container transformation advertised in crafting data. */
final readonly class ContainerMixData
{
    public function __construct(
        public int $inputItemId,
        public int $reagentItemId,
        public int $outputItemId,
    ) {
        foreach ([$this->inputItemId, $this->reagentItemId, $this->outputItemId] as $value) {
            if ($value < -0x80000000 || $value > 0x7fffffff) {
                throw new InvalidValueException('Container-mix fields must fit signed 32-bit integers.');
            }
        }
    }
}
