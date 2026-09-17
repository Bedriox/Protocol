<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

use Bedriox\Protocol\Exception\InvalidValueException;

final readonly class PlaceItemStackRequestAction implements ItemStackRequestAction
{
    public const int TYPE_ID = 1;

    public function __construct(
        public int $amount,
        public ItemStackRequestSlot $source,
        public ItemStackRequestSlot $destination,
    ) {
        if ($amount < 1 || $amount > 64) {
            throw new InvalidValueException('Place amount must be between 1 and 64.');
        }
    }

    public function typeId(): int { return self::TYPE_ID; }
}
