<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

use Bedriox\Protocol\Exception\InvalidValueException;
use Bedriox\Protocol\Exception\MalformedDataException;

final readonly class ResourcePackChunkRequestPacket implements Packet
{
    public function __construct(
        public string $packId,
        public string $packVersion,
        public int $chunkIndex,
    ) {
        CodecSupport::validateResourcePackIdentity($packId, $packVersion);
        if ($chunkIndex < 0 || $chunkIndex > 0x7fffffff) {
            throw new InvalidValueException('Resource-pack chunk index is outside its range.');
        }
    }

    public function packetId(): int
    {
        return PacketIds::RESOURCE_PACK_CHUNK_REQUEST;
    }

    public function encode(): string
    {
        return CodecSupport::writeResourcePackIdentity(CodecSupport::writer(), $this->packId, $this->packVersion)
            ->writeSignedIntLE($this->chunkIndex)
            ->toString();
    }

    public static function decode(string $bytes): self
    {
        [$packId, $packVersion, $reader] = CodecSupport::readResourcePackIdentity(CodecSupport::reader($bytes));
        $chunkIndex = $reader->readSignedIntLE();
        if ($chunkIndex->value < 0) {
            throw new MalformedDataException('Resource-pack chunk index cannot be negative.');
        }
        CodecSupport::requireEnd($chunkIndex->reader);
        return new self($packId, $packVersion, $chunkIndex->value);
    }
}
