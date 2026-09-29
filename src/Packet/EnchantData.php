<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

use Bedriox\Protocol\Exception\InvalidValueException;

final readonly class EnchantData
{
    public function __construct(public int $type, public int $level)
    {
        if ($type < 0 || $type > 0xffffffff || $level < 0 || $level > 0xff) {
            throw new InvalidValueException('Enchant data contains an out-of-range value.');
        }
    }
}
