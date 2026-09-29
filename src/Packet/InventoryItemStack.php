<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

use Bedriox\Protocol\Exception\InvalidValueException;

final readonly class InventoryItemStack
{
    public const int MAXIMUM_USER_DATA_BYTES = 102_400;

    public function __construct(
        public int $runtimeId,
        public int $count,
        public int $aux,
        public ?int $stackNetworkId,
        public int $blockRuntimeId,
        public string $userData,
    ) {
        if ($runtimeId < -0x8000 || $runtimeId > 0x7fff || $count < 0 || $count > 0xffff
            || $aux < 0 || $aux > 0x7fff || $blockRuntimeId < -0x80000000 || $blockRuntimeId > 0x7fffffff
            || ($stackNetworkId !== null && ($stackNetworkId < -0x80000000 || $stackNetworkId > 0x7fffffff))) {
            throw new InvalidValueException('Inventory item stack contains an out-of-range scalar.');
        }
        if (strlen($userData) > self::MAXIMUM_USER_DATA_BYTES) {
            throw new InvalidValueException('Inventory item user data exceeds its byte limit.');
        }
    }

    public static function empty(): self
    {
        return new self(0, 0, 0, null, 0, '');
    }
}
