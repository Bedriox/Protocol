<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

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
    }
}
