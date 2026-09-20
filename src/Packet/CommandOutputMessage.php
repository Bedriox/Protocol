<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

use Bedriox\Protocol\Exception\InvalidValueException;

final readonly class CommandOutputMessage
{
    public const int MAX_PARAMETERS = 64;

    /** @param list<string> $parameters */
    public function __construct(public string $messageId, public bool $internal = false, public array $parameters = [])
    {
        CodecSupport::validateString($messageId, CodecSupport::MAX_SHORT_STRING_BYTES, 'Command output message ID');
        CodecSupport::validateCount($parameters, self::MAX_PARAMETERS, 'Command output parameters');
        foreach ($parameters as $parameter) {
            if (!is_string($parameter)) {
                throw new InvalidValueException('Command output parameters must be strings.');
            }
            CodecSupport::validateString($parameter, CodecSupport::MAX_SHORT_STRING_BYTES, 'Command output parameter');
        }
    }
}
