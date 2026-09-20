<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

use Bedriox\Protocol\Exception\InvalidValueException;

final readonly class CommandOverload
{
    /** @param list<CommandParameter> $parameters */
    public function __construct(public array $parameters = [], public bool $chaining = false)
    {
        CodecSupport::validateCount($parameters, AvailableCommandsPacket::MAX_PARAMETERS, 'Command parameters');
        foreach ($parameters as $parameter) {
            if (!$parameter instanceof CommandParameter) {
                throw new InvalidValueException('Command parameters must be typed values.');
            }
        }
    }
}
