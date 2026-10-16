<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Exception;

final class UnsupportedPacketException extends CodecException
{
    public function __construct(public readonly int $packetId)
    {
        parent::__construct('Packet ID is not registered for decoding in the Bedrock codec.');
    }
}
