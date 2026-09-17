<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

/** Bedrock empty jigsaw registry encoded as one bounded network-NBT compound. */
final readonly class JigsawStructureDataPacket implements Packet
{
    private const string EMPTY_REGISTRY = "\x0a\x00"
        . "\x09\x0a" . 'processors' . "\x0a\x00"
        . "\x09\x0e" . 'template_pools' . "\x0a\x00"
        . "\x09\x07" . 'jigsaws' . "\x0a\x00"
        . "\x09\x0e" . 'structure_sets' . "\x0a\x00"
        . "\x00";

    public function packetId(): int
    {
        return PacketIds::JIGSAW_STRUCTURE_DATA;
    }

    public function encode(): string
    {
        return self::EMPTY_REGISTRY;
    }
}
