<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

final readonly class DropItemStackRequestAction extends RejectedItemStackRequestAction
{
    public const int TYPE_ID = ItemStackRequestActionType::Drop->value;

    public function __construct(
        int $amount,
        ItemStackRequestSlot $source,
        bool $randomly,
    ) {
        parent::__construct(self::TYPE_ID, $amount, $source, $randomly);
    }

    public function isRandomlySelected(): bool
    {
        return $this->randomly === true;
    }
}
