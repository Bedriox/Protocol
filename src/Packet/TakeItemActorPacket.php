<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

use Bedriox\Protocol\Value\UnsignedLong;

/** Shows one actor collecting a dropped-item actor. */
final readonly class TakeItemActorPacket implements Packet
{
    public function __construct(
        public UnsignedLong $itemRuntimeEntityId,
        public UnsignedLong $collectorRuntimeEntityId,
    ) {}

    public function packetId(): int { return PacketIds::TAKE_ITEM_ACTOR; }

    public function encode(): string
    {
        return CodecSupport::writer()->writeUnsignedVarLong($this->itemRuntimeEntityId)
            ->writeUnsignedVarLong($this->collectorRuntimeEntityId)->toString();
    }

    public static function decode(string $bytes): self
    {
        $item = CodecSupport::reader($bytes)->readUnsignedVarLong();
        $collector = $item->reader->readUnsignedVarLong();
        CodecSupport::requireEnd($collector->reader);
        return new self($item->value, $collector->value);
    }
}
