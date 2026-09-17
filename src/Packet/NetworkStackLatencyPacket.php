<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

use Bedriox\Protocol\Value\UnsignedLong;

final readonly class NetworkStackLatencyPacket implements Packet
{
    public function __construct(public UnsignedLong $creationTime, public bool $fromServer) {}
    public function packetId(): int { return PacketIds::NETWORK_STACK_LATENCY; }
    public function serverResponse(): self { return new self($this->creationTime, true); }

    public function encode(): string
    {
        return CodecSupport::writeBoolean(
            CodecSupport::writer()->writeUnsignedLongLE($this->creationTime),
            $this->fromServer,
        )->toString();
    }

    public static function decode(string $bytes): self
    {
        $time = CodecSupport::reader($bytes)->readUnsignedLongLE();
        [$fromServer, $reader] = CodecSupport::readBoolean($time->reader);
        CodecSupport::requireEnd($reader);
        return new self($time->value, $fromServer);
    }
}
