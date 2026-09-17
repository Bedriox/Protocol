<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

use Bedriox\Protocol\Exception\MalformedDataException;

final readonly class ResourcePackClientResponsePacket implements Packet
{
    /** @param list<string> $packIds */
    public function __construct(
        public ResourcePackResponseStatus $status,
        public array $packIds,
        public string $statusName = '',
    )
    {
        CodecSupport::validateString($statusName, CodecSupport::MAX_SHORT_STRING_BYTES, 'Resource-pack response status name');
        if ($status !== ResourcePackResponseStatus::SendPacks && $packIds !== []) {
            throw new \Bedriox\Protocol\Exception\InvalidValueException('Only the downloading response may include resource-pack IDs.');
        }
        CodecSupport::validateCount($packIds, CodecSupport::MAX_PACKS, 'Resource-pack response IDs');
        foreach ($packIds as $packId) {
            if (!is_string($packId)) {
                throw new \Bedriox\Protocol\Exception\InvalidValueException('Resource-pack response ID must be a string.');
            }
            CodecSupport::validateString($packId, CodecSupport::MAX_SHORT_STRING_BYTES, 'Resource-pack response ID');
        }
    }

    public function packetId(): int
    {
        return PacketIds::RESOURCE_PACK_CLIENT_RESPONSE;
    }

    public function encode(): string
    {
        $writer = CodecSupport::writer()->writeUnsignedVarInt($this->status->value)
            ->writeString($this->statusName, CodecSupport::MAX_SHORT_STRING_BYTES);
        if ($this->status === ResourcePackResponseStatus::SendPacks) {
            $writer = $writer->writeUnsignedVarInt(count($this->packIds));
            foreach ($this->packIds as $packId) {
                $writer = $writer->writeString($packId, CodecSupport::MAX_SHORT_STRING_BYTES);
            }
        }
        return $writer->toString();
    }

    public static function decode(string $bytes): self
    {
        $statusValue = CodecSupport::reader($bytes)->readUnsignedVarInt();
        $status = ResourcePackResponseStatus::tryFrom($statusValue->value);
        if ($status === null) {
            throw new MalformedDataException('Unknown resource-pack response status.');
        }
        $name = $statusValue->reader->readString(CodecSupport::MAX_SHORT_STRING_BYTES);
        $ids = [];
        $reader = $name->reader;
        if ($status === ResourcePackResponseStatus::SendPacks) {
            $count = $reader->readUnsignedVarInt();
            if ($count->value > CodecSupport::MAX_PACKS) {
                throw new MalformedDataException('Resource-pack response count exceeds its limit.');
            }
            $reader = $count->reader;
            for ($index = 0; $index < $count->value; ++$index) {
                $id = $reader->readString(CodecSupport::MAX_SHORT_STRING_BYTES);
                $ids[] = $id->value;
                $reader = $id->reader;
            }
        }
        CodecSupport::requireEnd($reader);
        return new self($status, $ids, $name->value);
    }
}
