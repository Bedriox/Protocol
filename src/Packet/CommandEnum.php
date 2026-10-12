<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

use Bedriox\Protocol\Exception\InvalidValueException;

/** A named hard or soft enum used by a command parameter. */
final readonly class CommandEnum
{
    /**
     * @param list<string> $values
     * @param array<string, list<CommandEnumConstraint>> $constraints
     */
    public function __construct(
        public string $name,
        public array $values,
        public bool $soft = false,
        public array $constraints = [],
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
        if ($soft && $constraints !== []) {
            throw new InvalidValueException('Soft command enums cannot carry semantic constraints.');
        }
        foreach ($constraints as $value => $valueConstraints) {
            if (!is_string($value) || !isset($seen[$value])) {
                throw new InvalidValueException('Command enum constraints must reference a declared value.');
            }
            CodecSupport::validateCount(
                $valueConstraints,
                AvailableCommandsPacket::MAX_CONSTRAINTS_PER_VALUE,
                'Command enum value constraints',
            );
            if ($valueConstraints === []) {
                throw new InvalidValueException('Command enum constraint lists cannot be empty.');
            }
            $constraintIds = [];
            foreach ($valueConstraints as $constraint) {
                if (!$constraint instanceof CommandEnumConstraint || isset($constraintIds[$constraint->value])) {
                    throw new InvalidValueException('Command enum constraints must be unique typed values.');
                }
                $constraintIds[$constraint->value] = true;
            }
        }
    }

    /** @return list<CommandEnumConstraint> */
    public function constraintsFor(string $value): array
    {
        return $this->constraints[$value] ?? [];
    }
}
