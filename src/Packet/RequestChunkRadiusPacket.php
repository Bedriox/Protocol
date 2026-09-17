<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

use Bedriox\Protocol\Exception\InvalidValueException;
use Bedriox\Protocol\Exception\MalformedDataException;

final readonly class RequestChunkRadiusPacket implements Packet
{
    public function __construct(public int $radius, public int $maximumRadius)
    {
        if ($radius < 0 || $radius > 0x7fffffff || $maximumRadius < 0 || $maximumRadius > 255) {
            throw new InvalidValueException('Chunk radii exceed the bounded MVP range.');
        }
    }

    public function packetId(): int { return PacketIds::REQUEST_CHUNK_RADIUS; }
    public function encode(): string { return CodecSupport::writer()->writeSignedVarInt($this->radius)->writeUnsignedByte($this->maximumRadius)->toString(); }

    public static function decode(string $bytes): self
    {
        $radius = CodecSupport::reader($bytes)->readSignedVarInt();
        $maximum = $radius->reader->readUnsignedByte();
        CodecSupport::requireEnd($maximum->reader);
        if ($radius->value < 0) {
            throw new MalformedDataException('Requested chunk radius exceeds the bounded MVP range.');
        }
        return new self($radius->value, $maximum->value);
    }
}
