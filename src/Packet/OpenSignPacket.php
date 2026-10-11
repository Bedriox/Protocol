<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

/** Opens the editor for one side of a sign block. */
final readonly class OpenSignPacket implements Packet
{
    public function __construct(public BlockPosition $position, public bool $front) {}

    public function packetId(): int
    {
        return PacketIds::OPEN_SIGN;
    }

    public function encode(): string
    {
        $writer = CodecSupport::writer()
            ->writeSignedVarInt($this->position->x)
            ->writeSignedVarInt($this->position->y)
            ->writeSignedVarInt($this->position->z);
        return CodecSupport::writeBoolean($writer, $this->front)->toString();
    }

    public static function decode(string $bytes): self
    {
        $x = CodecSupport::reader($bytes)->readSignedVarInt();
        $y = $x->reader->readSignedVarInt();
        $z = $y->reader->readSignedVarInt();
        [$front, $reader] = CodecSupport::readBoolean($z->reader);
        CodecSupport::requireEnd($reader);

        return new self(new BlockPosition($x->value, $y->value, $z->value), $front);
    }
}
