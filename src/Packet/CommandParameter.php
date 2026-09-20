<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

use Bedriox\Protocol\Exception\InvalidValueException;

final readonly class CommandParameter
{
    /** @param list<CommandParameterOption> $options */
    public function __construct(
        public string $name,
        public CommandArgumentType $type,
        public bool $optional = false,
        public array $options = [],
    ) {
        CodecSupport::validateString($name, CodecSupport::MAX_SHORT_STRING_BYTES, 'Command parameter name');
        if (!array_is_list($options)) {
            throw new InvalidValueException('Command parameter options must be a list.');
        }
        $bits = 0;
        foreach ($options as $option) {
            if (!$option instanceof CommandParameterOption || ($bits & (1 << $option->value)) !== 0) {
                throw new InvalidValueException('Command parameter options must be unique typed values.');
            }
            $bits |= 1 << $option->value;
        }
    }
}
