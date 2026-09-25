<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

final readonly class CraftNonImplementedItemStackRequestAction implements ItemStackRequestAction
{
    public const int TYPE_ID = ItemStackRequestActionType::CraftNonImplemented->value;

    public function typeId(): int { return self::TYPE_ID; }
}
