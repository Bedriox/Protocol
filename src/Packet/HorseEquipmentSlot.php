<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

use Bedriox\Protocol\Exception\InvalidValueException;

/** One bounded horse-family equipment slot declaration. */
final readonly class HorseEquipmentSlot
{
    /** @param list<string> $acceptedItemIdentifiers */
    public function __construct(
        public int $slotNumber,
        public array $acceptedItemIdentifiers,
        public ?string $equippedItemIdentifier = null,
    ) {
        if ($slotNumber < 0 || $slotNumber > 53 || count($acceptedItemIdentifiers) > 128) {
            throw new InvalidValueException('Horse equipment-slot declaration is outside its supported bounds.');
        }
        $seen = [];
        foreach ($acceptedItemIdentifiers as $identifier) {
            if (!is_string($identifier) || preg_match('/^[a-z0-9_.-]+:[a-z0-9_.\/-]+$/D', $identifier) !== 1
                || strlen($identifier) > 256) {
                throw new InvalidValueException('Horse equipment-slot item identifier is invalid.');
            }
            if (isset($seen[$identifier])) {
                throw new InvalidValueException('Horse equipment-slot accepted items are duplicated.');
            }
            $seen[$identifier] = true;
        }
        if ($equippedItemIdentifier !== null
            && (preg_match('/^[a-z0-9_.-]+:[a-z0-9_.\/-]+$/D', $equippedItemIdentifier) !== 1
                || strlen($equippedItemIdentifier) > 256
                || !isset($seen[$equippedItemIdentifier]))) {
            throw new InvalidValueException('Equipped horse item identifier is invalid.');
        }
    }
}
