<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

/** Closes the form currently displayed by the client. */
final readonly class ClientboundCloseFormPacket implements Packet
{
    public function packetId(): int { return PacketIds::CLIENTBOUND_CLOSE_FORM; }

    public function encode(): string { return ''; }

    public static function decode(string $bytes): self
    {
        CodecSupport::requireEnd(CodecSupport::reader($bytes));
        return new self();
    }
}
