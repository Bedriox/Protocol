<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

use Bedriox\Protocol\Exception\InvalidValueException;

final readonly class ItemStackResponseSlot
{
    public function __construct(
        public int $requestedSlot,
        public int $slot,
        public int $amount,
        public ?int $stackNetworkId,
        public string $customName = '',
        public ?string $filteredCustomName = null,
        public int $durabilityCorrection = 0,
    ) {
        if ($requestedSlot < 0 || $requestedSlot > 0xff || $slot < 0 || $slot > 0xff
            || $amount < 0 || $amount > 0xff
            || ($stackNetworkId !== null && ($stackNetworkId < -0x80000000 || $stackNetworkId > 0x7fffffff))
            || $durabilityCorrection < -0x8000 || $durabilityCorrection > 0x7fff) {
            throw new InvalidValueException('Item-stack response slot contains an out-of-range scalar.');
        }
        if (($amount === 0) !== ($stackNetworkId === null)) {
            throw new InvalidValueException('Item-stack response network ID presence must match its amount.');
        }
        CodecSupport::validateString($customName, CodecSupport::MAX_SHORT_STRING_BYTES, 'Item-stack response custom name');
        if ($filteredCustomName !== null) {
            CodecSupport::validateString($filteredCustomName, CodecSupport::MAX_SHORT_STRING_BYTES, 'Item-stack response filtered custom name');
        }
    }
}
