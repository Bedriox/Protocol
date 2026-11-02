<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

use Bedriox\Protocol\Exception\MalformedDataException;

final readonly class ServerboundCursorItemDragPacket implements Packet
{
    public function __construct(public CursorItemDragState $state) {}
    public function packetId(): int { return PacketIds::SERVERBOUND_CURSOR_ITEM_DRAG; }
    public function encode(): string { return chr($this->state->value); }
    public static function decode(string $bytes): self
    {
        $state = CodecSupport::reader($bytes)->readUnsignedByte();
        CodecSupport::requireEnd($state->reader);
        return new self(CursorItemDragState::tryFrom($state->value)
            ?? throw new MalformedDataException('Cursor-item drag state is unknown.'));
    }
}
