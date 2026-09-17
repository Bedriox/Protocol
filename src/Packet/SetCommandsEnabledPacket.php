<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

final readonly class SetCommandsEnabledPacket implements Packet
{
    public function __construct(public bool $enabled = true) {}
    public function packetId(): int { return PacketIds::SET_COMMANDS_ENABLED; }
    public function encode(): string { return CodecSupport::writeBoolean(CodecSupport::writer(), $this->enabled)->toString(); }
}
