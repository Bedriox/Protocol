<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

use Bedriox\Protocol\Exception\InvalidValueException;

final readonly class CreativeItemEntry
{
    public function __construct(
        public int $networkId,
        public InventoryItemStack $item,
        public int $groupId = 0,
    ) {
        if ($networkId < 1 || $networkId > 0xffffffff || $groupId < 0 || $groupId > 0xffffffff) {
            throw new InvalidValueException('Creative item identifiers are outside their unsigned 32-bit ranges.');
        }
        CreativeItemStackWireCodec::validate($item);
    }
}
