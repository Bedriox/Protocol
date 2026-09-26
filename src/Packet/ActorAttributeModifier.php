<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

use Bedriox\Protocol\Exception\InvalidValueException;

final readonly class ActorAttributeModifier
{
    public function __construct(
        public string $id,
        public string $name,
        public float $amount,
        public ActorAttributeOperation $operation,
        public int $operand,
        public bool $serializable,
    ) {
        CodecSupport::validateString($id, 128, 'Actor attribute modifier ID');
        CodecSupport::validateString($name, 128, 'Actor attribute modifier name');
        if ($id === '') {
            throw new InvalidValueException('Actor attribute modifier ID cannot be empty.');
        }
        CodecSupport::validateFiniteFloat($amount, 'Actor attribute modifier amount');
        if ($operand < -0x80000000 || $operand > 0x7fffffff) {
            throw new InvalidValueException('Actor attribute modifier operand must fit a signed 32-bit integer.');
        }
    }
}
