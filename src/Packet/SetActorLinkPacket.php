<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

final readonly class SetActorLinkPacket implements Packet
{
    public function __construct(public ActorLink $link)
    {
    }

    public function packetId(): int
    {
        return PacketIds::SET_ACTOR_LINK;
    }

    public function encode(): string
    {
        return $this->link->write(CodecSupport::writer())->toString();
    }

    public static function decode(string $bytes): self
    {
        [$link, $reader] = ActorLink::read(CodecSupport::reader($bytes));
        CodecSupport::requireEnd($reader);
        return new self($link);
    }
}
