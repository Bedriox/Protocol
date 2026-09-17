<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

use Bedriox\Protocol\Exception\InvalidValueException;

final readonly class ItemStackRequestSlot
{
    public function __construct(
        public FullContainerName $containerName,
        public int $slot,
        public int $stackNetworkId,
    ) {
        if ($slot < 0 || $slot > 0xff
            || $stackNetworkId < -0x80000000 || $stackNetworkId > 0x7fffffff) {
            throw new InvalidValueException('Item-stack request slot contains an out-of-range value.');
        }
    }
}
