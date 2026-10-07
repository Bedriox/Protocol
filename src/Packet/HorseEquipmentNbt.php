<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

use Bedriox\Protocol\Codec\SignedVarInt;
use Bedriox\Protocol\Codec\UnsignedVarInt;
use Bedriox\Protocol\Exception\InvalidValueException;

/** Builds the bounded network-NBT slot declaration consumed by UpdateEquip. */
final class HorseEquipmentNbt
{
    /** @param list<HorseEquipmentSlot> $slots */
    public static function encode(array $slots): string
    {
        if (count($slots) > 8) {
            throw new InvalidValueException('Horse equipment-slot count exceeds its supported bound.');
        }
        $seen = [];
        $payload = "\x0a\x00" . self::named(9, 'slots') . "\x0a" . SignedVarInt::encode(count($slots));
        foreach ($slots as $slot) {
            if (!$slot instanceof HorseEquipmentSlot || isset($seen[$slot->slotNumber])) {
                throw new InvalidValueException('Horse equipment slots must be typed and unique.');
            }
            $seen[$slot->slotNumber] = true;
            $payload .= self::named(3, 'slotNumber') . SignedVarInt::encode($slot->slotNumber);
            $payload .= self::named(9, 'acceptedItems') . "\x0a"
                . SignedVarInt::encode(count($slot->acceptedItemIdentifiers));
            foreach ($slot->acceptedItemIdentifiers as $identifier) {
                $payload .= self::named(8, 'Name') . self::string($identifier) . "\x00";
            }
            if ($slot->equippedItemIdentifier !== null) {
                $payload .= self::named(10, 'item')
                    . self::named(8, 'Name') . self::string($slot->equippedItemIdentifier)
                    . self::named(2, 'Aux') . pack('v', 0x7fff)
                    . "\x00";
            }
            $payload .= "\x00";
        }
        $payload .= "\x00";
        NetworkNbtCompound::validate($payload);

        return $payload;
    }

    private static function named(int $type, string $name): string
    {
        return chr($type) . self::string($name);
    }

    private static function string(string $value): string
    {
        return UnsignedVarInt::encode(strlen($value)) . $value;
    }
}
