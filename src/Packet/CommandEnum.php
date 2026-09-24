<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

use Bedriox\Protocol\Exception\InvalidValueException;

/** A named hard or soft enum used by a command parameter. */
final readonly class CommandEnum
{
    /** @param list<string> $values */
    public function __construct(
        public string $name,
        public array $values,
        public bool $soft = false,
    ) {
        CodecSupport::validateString($name, CodecSupport::MAX_SHORT_STRING_BYTES, 'Command enum name');
        if ($name === '') {
            throw new InvalidValueException('Command enum name cannot be empty.');
        }
        CodecSupport::validateCount($values, AvailableCommandsPacket::MAX_ENUM_VALUES, 'Command enum values');
        if (!$soft && $values === []) {
            throw new InvalidValueException('Hard command enums require at least one value.');
        }
        $seen = [];
        foreach ($values as $value) {
            if (!is_string($value)) {
                throw new InvalidValueException('Command enum values must be strings.');
            }
            CodecSupport::validateString($value, CodecSupport::MAX_SHORT_STRING_BYTES, 'Command enum value');
            if ($value === '' || isset($seen[$value])) {
                throw new InvalidValueException('Command enum values must be non-empty and unique.');
            }
            $seen[$value] = true;
        }
    }
}
