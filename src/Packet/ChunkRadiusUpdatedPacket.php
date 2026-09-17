<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

use Bedriox\Protocol\Exception\InvalidValueException;
use Bedriox\Protocol\Exception\MalformedDataException;

final readonly class ChunkRadiusUpdatedPacket implements Packet
{
    public function __construct(public int $radius)
    {
        if ($radius < 0 || $radius > 32) {
            throw new InvalidValueException('Chunk radius exceeds the bounded MVP range.');
        }
    }

    public function packetId(): int { return PacketIds::CHUNK_RADIUS_UPDATED; }
    public function encode(): string { return CodecSupport::writer()->writeSignedVarInt($this->radius)->toString(); }

    public static function decode(string $bytes): self
    {
        $radius = CodecSupport::reader($bytes)->readSignedVarInt();
        CodecSupport::requireEnd($radius->reader);
        if ($radius->value < 0 || $radius->value > 32) {
            throw new MalformedDataException('Chunk radius exceeds the bounded MVP range.');
        }
        return new self($radius->value);
    }
}
