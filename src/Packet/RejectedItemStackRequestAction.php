<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

use Bedriox\Protocol\Exception\InvalidValueException;

/** A structurally known common action retained so consumers can reject it by request ID. */
final readonly class RejectedItemStackRequestAction implements ItemStackRequestAction
{
    public function __construct(
        private int $type,
        public int $amount,
        public ItemStackRequestSlot $source,
        public ?bool $randomly = null,
    ) {
        if ($type < 3 || $type > 5 || $amount < 1 || $amount > 64
            || (($type === 3) !== ($randomly !== null))) {
            throw new InvalidValueException('Rejected item-stack request action is invalid.');
        }
    }

    public function typeId(): int { return $this->type; }
}
