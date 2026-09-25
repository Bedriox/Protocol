<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

final readonly class SwapItemStackRequestAction implements ItemStackRequestAction
{
    public const int TYPE_ID = ItemStackRequestActionType::Swap->value;

    public function __construct(
        public ItemStackRequestSlot $source,
        public ItemStackRequestSlot $destination,
    ) {}

    public function typeId(): int { return self::TYPE_ID; }
}
