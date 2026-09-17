<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

final readonly class ServerToClientHandshakePacket implements Packet
{
    public function __construct(public string $jwt)
    {
        CodecSupport::validateString($jwt, CodecSupport::MAX_JWT_BYTES, 'Handshake JWT');
    }

    public function packetId(): int
    {
        return PacketIds::SERVER_TO_CLIENT_HANDSHAKE;
    }

    public function encode(): string
    {
        return CodecSupport::writer()->writeString($this->jwt, CodecSupport::MAX_JWT_BYTES)->toString();
    }

    public static function decode(string $bytes): self
    {
        $jwt = CodecSupport::reader($bytes)->readString(CodecSupport::MAX_JWT_BYTES);
        CodecSupport::requireEnd($jwt->reader);
        return new self($jwt->value);
    }
}
