<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

use Bedriox\Protocol\Exception\InvalidValueException;

final readonly class ItemStackResponseContainer
{
    public const int MAXIMUM_SLOTS = 128;

    /** @param list<ItemStackResponseSlot> $slots */
    public function __construct(public FullContainerName $containerName, public array $slots)
    {
        CodecSupport::validateCount($slots, self::MAXIMUM_SLOTS, 'Item-stack response slots');
        foreach ($slots as $slot) {
            if (!$slot instanceof ItemStackResponseSlot) {
                throw new InvalidValueException('Item-stack response slots must be typed values.');
            }
        }
    }
}
