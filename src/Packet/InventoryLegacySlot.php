<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

use Bedriox\Protocol\Exception\InvalidValueException;

final readonly class InventoryLegacySlot
{
    public const int MAXIMUM_SLOTS = 89;

    /** Slots are retained as their exact unsigned-byte sequence. */
    public function __construct(public int $containerId, public string $slots)
    {
        if ($containerId < 0 || $containerId > 0xff) {
            throw new InvalidValueException('Legacy inventory container ID must fit an unsigned byte.');
        }
        if (strlen($slots) > self::MAXIMUM_SLOTS) {
            throw new InvalidValueException('Legacy inventory slot list exceeds its limit.');
        }
    }
}
