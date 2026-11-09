<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

use Bedriox\Protocol\Exception\InvalidValueException;
use Bedriox\Protocol\Exception\MalformedDataException;

final readonly class ResourcePackChunkDataPacket implements Packet
{
    public function __construct(
        public string $packId,
        public string $packVersion,
        public int $chunkIndex,
        public int $progress,
        public string $data,
    ) {
        CodecSupport::validateResourcePackIdentity($packId, $packVersion);
        if ($chunkIndex < 0 || $chunkIndex > 0x7fffffff) {
            throw new InvalidValueException('Resource-pack chunk index is outside its range.');
        }
        if ($progress < 0) {
            throw new InvalidValueException('Resource-pack chunk progress cannot be negative.');
        }
        $dataLength = strlen($data);
        if ($dataLength < 1 || $dataLength > CodecSupport::MAX_RESOURCE_PACK_CHUNK_BYTES) {
            throw new InvalidValueException('Resource-pack chunk data is outside its byte limit.');
        }
    }

    public function packetId(): int
    {
        return PacketIds::RESOURCE_PACK_CHUNK_DATA;
    }

    public function encode(): string
    {
        return CodecSupport::writeResourcePackIdentity(CodecSupport::writer(), $this->packId, $this->packVersion)
            ->writeSignedIntLE($this->chunkIndex)
            ->writeSignedLongLE($this->progress)
            ->writeUnsignedVarInt(strlen($this->data))
            ->writeBytes($this->data)
            ->toString();
    }

    public static function decode(string $bytes): self
    {
        [$packId, $packVersion, $reader] = CodecSupport::readResourcePackIdentity(CodecSupport::reader($bytes));
        $chunkIndex = $reader->readSignedIntLE();
        if ($chunkIndex->value < 0) {
            throw new MalformedDataException('Resource-pack chunk index cannot be negative.');
        }
        $progress = $chunkIndex->reader->readSignedLongLE();
        if ($progress->value < 0) {
            throw new MalformedDataException('Resource-pack chunk progress cannot be negative.');
        }
        $dataLength = $progress->reader->readUnsignedVarInt();
        if ($dataLength->value < 1 || $dataLength->value > CodecSupport::MAX_RESOURCE_PACK_CHUNK_BYTES) {
            throw new MalformedDataException('Resource-pack chunk data is outside its byte limit.');
        }
        $data = $dataLength->reader->readBytes($dataLength->value);
        CodecSupport::requireEnd($data->reader);
        return new self($packId, $packVersion, $chunkIndex->value, $progress->value, $data->value);
    }
}
