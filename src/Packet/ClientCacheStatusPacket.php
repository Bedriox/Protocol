<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

final readonly class ClientCacheStatusPacket implements Packet
{
    public function __construct(public bool $supported) {}

    public function packetId(): int
    {
        return PacketIds::CLIENT_CACHE_STATUS;
    }

    public function encode(): string
    {
        return CodecSupport::writeBoolean(CodecSupport::writer(), $this->supported)->toString();
    }

    public static function decode(string $bytes): self
    {
        [$supported, $reader] = CodecSupport::readBoolean(CodecSupport::reader($bytes));
        CodecSupport::requireEnd($reader);

        return new self($supported);
    }
}
