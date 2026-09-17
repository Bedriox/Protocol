<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

use Bedriox\Protocol\Exception\InvalidValueException;
use Bedriox\Protocol\Exception\MalformedDataException;

final readonly class NetworkChunkPublisherUpdatePacket implements Packet
{
    /** @param list<ChunkPosition> $savedChunks */
    public function __construct(public int $x, public int $y, public int $z, public int $radius, public array $savedChunks = [])
    {
        foreach ([$x, $y, $z] as $value) {
            if ($value < -0x80000000 || $value > 0x7fffffff) {
                throw new InvalidValueException('Publisher coordinate must fit in a signed 32-bit integer.');
            }
        }
        if ($radius < 0 || $radius > 512) {
            throw new InvalidValueException('Publisher radius exceeds the bounded MVP range.');
        }
        CodecSupport::validateCount($savedChunks, CodecSupport::MAX_SAVED_CHUNKS, 'Saved chunks');
        foreach ($savedChunks as $chunk) {
            if (!$chunk instanceof ChunkPosition) {
                throw new InvalidValueException('Saved chunk entries must be ChunkPosition values.');
            }
        }
    }

    public function packetId(): int { return PacketIds::NETWORK_CHUNK_PUBLISHER_UPDATE; }

    public function encode(): string
    {
        $writer = CodecSupport::writer()->writeSignedVarInt($this->x)->writeSignedVarInt($this->y)->writeSignedVarInt($this->z)
            ->writeUnsignedVarInt($this->radius)->writeSignedIntLE(count($this->savedChunks));
        foreach ($this->savedChunks as $chunk) {
            $writer = $writer->writeSignedVarInt($chunk->x)->writeSignedVarInt($chunk->z);
        }
        return $writer->toString();
    }

    public static function decode(string $bytes): self
    {
        $x = CodecSupport::reader($bytes)->readSignedVarInt();
        $y = $x->reader->readSignedVarInt();
        $z = $y->reader->readSignedVarInt();
        $radius = $z->reader->readUnsignedVarInt();
        if ($radius->value > 512) {
            throw new MalformedDataException('Publisher radius exceeds the bounded MVP range.');
        }
        $count = $radius->reader->readSignedIntLE();
        if ($count->value < 0 || $count->value > CodecSupport::MAX_SAVED_CHUNKS) {
            throw new MalformedDataException('Saved chunk count exceeds its limit.');
        }
        $chunks = [];
        $reader = $count->reader;
        for ($i = 0; $i < $count->value; ++$i) {
            $cx = $reader->readSignedVarInt();
            $cz = $cx->reader->readSignedVarInt();
            $chunks[] = new ChunkPosition($cx->value, $cz->value);
            $reader = $cz->reader;
        }
        CodecSupport::requireEnd($reader);
        return new self($x->value, $y->value, $z->value, $radius->value, $chunks);
    }
}
