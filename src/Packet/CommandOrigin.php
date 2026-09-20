<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

use Bedriox\Protocol\Exception\InvalidValueException;

final readonly class CommandOrigin
{
    public function __construct(
        public CommandOriginType $type,
        public string $uuid,
        public string $requestId,
        public int $playerId = -1,
    ) {
        CodecSupport::uuidToWire($uuid);
        CodecSupport::validateString($requestId, CodecSupport::MAX_SHORT_STRING_BYTES, 'Command request ID');
        if ($type !== CommandOriginType::DevConsole && $type !== CommandOriginType::Test && $playerId !== -1) {
            throw new InvalidValueException('Only development-console and test command origins carry a player ID.');
        }
    }
}
