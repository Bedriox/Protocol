<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

/** Exact-empty serverbound notification; no response is implied by this value. */
final readonly class ServerSettingsRequestPacket implements Packet
{
    public function packetId(): int { return PacketIds::SERVER_SETTINGS_REQUEST; }

    public function encode(): string { return ''; }

    public static function decode(string $bytes): self
    {
        CodecSupport::requireEnd(CodecSupport::reader($bytes));
        return new self();
    }
}
