<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

use Bedriox\Protocol\Exception\InvalidValueException;
use Bedriox\Protocol\Exception\MalformedDataException;

final readonly class ResourcePackDataInfoPacket implements Packet
{
    public function __construct(
        public string $packId,
        public string $packVersion,
        public int $maximumChunkBytes,
        public int $chunkCount,
        public int $compressedPackSize,
        public string $sha256,
        public bool $premium,
        public ResourcePackType $type,
    ) {
        CodecSupport::validateResourcePackIdentity($packId, $packVersion);
        if ($maximumChunkBytes < 1 || $maximumChunkBytes > CodecSupport::MAX_RESOURCE_PACK_CHUNK_BYTES) {
            throw new InvalidValueException('Resource-pack maximum chunk size is outside its limit.');
        }
        if ($chunkCount < 1 || $chunkCount > CodecSupport::MAX_RESOURCE_PACK_CHUNKS) {
            throw new InvalidValueException('Resource-pack chunk count is outside its limit.');
        }
        if ($compressedPackSize < 0) {
            throw new InvalidValueException('Resource-pack compressed size cannot be negative.');
        }
        if (strlen($sha256) !== CodecSupport::RESOURCE_PACK_HASH_BYTES) {
            throw new InvalidValueException('Resource-pack hash must contain exactly 32 bytes.');
        }
    }

    public function packetId(): int
    {
        return PacketIds::RESOURCE_PACK_DATA_INFO;
    }

    public function encode(): string
    {
        $writer = CodecSupport::writeResourcePackIdentity(CodecSupport::writer(), $this->packId, $this->packVersion)
            ->writeUnsignedIntLE($this->maximumChunkBytes)
            ->writeUnsignedIntLE($this->chunkCount)
            ->writeSignedLongLE($this->compressedPackSize)
            ->writeUnsignedVarInt(strlen($this->sha256))
            ->writeBytes($this->sha256);
        $writer = CodecSupport::writeBoolean($writer, $this->premium)
            ->writeUnsignedByte($this->type->value);
        return $writer->toString();
    }

    public static function decode(string $bytes): self
    {
        [$packId, $packVersion, $reader] = CodecSupport::readResourcePackIdentity(CodecSupport::reader($bytes));
        $maximumChunkBytes = $reader->readUnsignedIntLE();
        if ($maximumChunkBytes->value < 1 || $maximumChunkBytes->value > CodecSupport::MAX_RESOURCE_PACK_CHUNK_BYTES) {
            throw new MalformedDataException('Resource-pack maximum chunk size is outside its limit.');
        }
        $chunkCount = $maximumChunkBytes->reader->readUnsignedIntLE();
        if ($chunkCount->value < 1 || $chunkCount->value > CodecSupport::MAX_RESOURCE_PACK_CHUNKS) {
            throw new MalformedDataException('Resource-pack chunk count is outside its limit.');
        }
        $compressedPackSize = $chunkCount->reader->readSignedLongLE();
        if ($compressedPackSize->value < 0) {
            throw new MalformedDataException('Resource-pack compressed size cannot be negative.');
        }
        $hashLength = $compressedPackSize->reader->readUnsignedVarInt();
        if ($hashLength->value !== CodecSupport::RESOURCE_PACK_HASH_BYTES) {
            throw new MalformedDataException('Resource-pack hash must contain exactly 32 bytes.');
        }
        $hash = $hashLength->reader->readBytes($hashLength->value);
        [$premium, $reader] = CodecSupport::readBoolean($hash->reader);
        $typeValue = $reader->readUnsignedByte();
        $type = ResourcePackType::tryFrom($typeValue->value);
        if ($type === null) {
            throw new MalformedDataException('Unknown resource-pack type.');
        }
        CodecSupport::requireEnd($typeValue->reader);
        return new self(
            $packId,
            $packVersion,
            $maximumChunkBytes->value,
            $chunkCount->value,
            $compressedPackSize->value,
            $hash->value,
            $premium,
            $type,
        );
    }
}
