<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

use Bedriox\Protocol\Exception\InvalidValueException;

final readonly class CraftCreativeItemStackRequestAction implements ItemStackRequestAction
{
    public const int TYPE_ID = ItemStackRequestActionType::CraftCreative->value;

    public function __construct(
        public int $creativeItemNetworkId,
        public int $requestedCrafts,
    ) {
        if ($creativeItemNetworkId < 1 || $creativeItemNetworkId > 0xffffffff
            || $requestedCrafts < 1 || $requestedCrafts > 0xff) {
            throw new InvalidValueException('Creative-craft action contains an out-of-range value.');
        }
    }

    public function typeId(): int { return self::TYPE_ID; }
}
