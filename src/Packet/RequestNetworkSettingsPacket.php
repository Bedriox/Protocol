<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

use Bedriox\Protocol\ProtocolVersion;

final readonly class RequestNetworkSettingsPacket implements Packet
{
    public function __construct(public int $protocolVersion = ProtocolVersion::CURRENT)
    {
    }

    public function packetId(): int
    {
        return PacketIds::REQUEST_NETWORK_SETTINGS;
    }

    public function encode(): string
    {
        return CodecSupport::writeSignedIntBE(CodecSupport::writer(), $this->protocolVersion)->toString();
    }

    public static function decode(string $bytes): self
    {
        [$version, $reader] = CodecSupport::readSignedIntBE(CodecSupport::reader($bytes));
        CodecSupport::requireEnd($reader);
        return new self($version);
    }
}
