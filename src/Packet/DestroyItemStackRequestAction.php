<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

final readonly class DestroyItemStackRequestAction extends RejectedItemStackRequestAction
{
    public const int TYPE_ID = ItemStackRequestActionType::Destroy->value;

    public function __construct(int $amount, ItemStackRequestSlot $source)
    {
        parent::__construct(self::TYPE_ID, $amount, $source);
    }
}
