<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

use Bedriox\Protocol\Exception\InvalidValueException;

/** A bounded, non-authoritative item descriptor in a deprecated craft-results action. */
final readonly class ItemStackRequestResultItem
{
    public function __construct(
        public int $descriptorType,
        public ?string $descriptorValue,
        public int $auxOrVersion,
        public int $count,
        public int $blockRuntimeId,
        public string $userData,
    ) {
        if ($descriptorType < 0 || $descriptorType > 3
            || (($descriptorType === 0) !== ($descriptorValue === null))
            || ($descriptorValue !== null && (strlen($descriptorValue) > CodecSupport::MAX_SHORT_STRING_BYTES
                || preg_match('//u', $descriptorValue) !== 1))
            || $auxOrVersion < -0x80000000 || $auxOrVersion > 0x7fffffff
            || ($descriptorType === 2 && ($auxOrVersion < -0x8000 || $auxOrVersion > 0x7fff))
            || $count < 0 || $count > 0xffff
            || $blockRuntimeId < 0 || $blockRuntimeId > 0xffffffff
            || strlen($userData) > InventoryItemStack::MAXIMUM_USER_DATA_BYTES) {
            throw new InvalidValueException('Craft-results item descriptor is invalid.');
        }
    }
}
