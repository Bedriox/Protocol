<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

use Bedriox\Protocol\Value\UnsignedLong;

final readonly class SetLocalPlayerAsInitializedPacket implements Packet
{
    public function __construct(public UnsignedLong $runtimeEntityId) {}
    public function packetId(): int { return PacketIds::SET_LOCAL_PLAYER_AS_INITIALIZED; }
    public function encode(): string { return CodecSupport::writer()->writeUnsignedVarLong($this->runtimeEntityId)->toString(); }

    public static function decode(string $bytes): self
    {
        $id = CodecSupport::reader($bytes)->readUnsignedVarLong();
        CodecSupport::requireEnd($id->reader);
        return new self($id->value);
    }
}
