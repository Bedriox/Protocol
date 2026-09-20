<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

use Bedriox\Protocol\Exception\InvalidValueException;

final readonly class CommandDefinition
{
    /** @param list<CommandOverload> $overloads */
    public function __construct(
        public string $name,
        public string $description,
        public CommandPermission $permission = CommandPermission::Any,
        public array $overloads = [],
        public int $flags = 0,
    ) {
        CodecSupport::validateString($name, CodecSupport::MAX_SHORT_STRING_BYTES, 'Command name');
        CodecSupport::validateString($description, CodecSupport::MAX_SHORT_STRING_BYTES, 'Command description');
        if ($name === '' || preg_match('/^[a-z0-9:_-]+$/D', $name) !== 1) {
            throw new InvalidValueException('Command name contains unsupported characters.');
        }
        if ($flags < 0 || $flags > 0xffff) {
            throw new InvalidValueException('Command flags must fit an unsigned 16-bit integer.');
        }
        CodecSupport::validateCount($overloads, AvailableCommandsPacket::MAX_OVERLOADS, 'Command overloads');
        foreach ($overloads as $overload) {
            if (!$overload instanceof CommandOverload) {
                throw new InvalidValueException('Command overloads must be typed values.');
            }
        }
    }
}
