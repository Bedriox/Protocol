<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

final readonly class ClientToServerHandshakePacket implements Packet
{
    public function packetId(): int
    {
        return PacketIds::CLIENT_TO_SERVER_HANDSHAKE;
    }

    public function encode(): string
    {
        return '';
    }

    public static function decode(string $bytes): self
    {
        CodecSupport::requireEnd(CodecSupport::reader($bytes));
        return new self();
    }
}
