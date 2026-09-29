<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

use Bedriox\Protocol\Exception\InvalidValueException;
use Bedriox\Protocol\Exception\MalformedDataException;

/** Bounded non-cache Bedrock level-chunk envelope. */
final readonly class LevelChunkPacket implements Packet
{
    private const int MAX_SUB_CHUNK_COUNT = 64;
    private const int MAX_CACHE_METADATA_COUNT = 65;
    public const int OVERWORLD_MIN_SECTION_Y = -4;
    public const int OVERWORLD_MAX_SECTION_Y = 19;
    public const int FIXED_FLAT_SECTION_Y = 3;
    public const int OVERWORLD_SECTION_COUNT = self::OVERWORLD_MAX_SECTION_Y - self::OVERWORLD_MIN_SECTION_Y + 1;
    public const int FIXED_FLAT_SUB_CHUNK_LIMIT = self::FIXED_FLAT_SECTION_Y - self::OVERWORLD_MIN_SECTION_Y + 1;

    public function __construct(
        public int $chunkX,
        public int $chunkZ,
        public int $dimension,
        public int $subChunkCount,
        public string $data,
        public bool $requestSubChunks = false,
        public int $subChunkLimit = 0,
    ) {
        foreach ([$chunkX, $chunkZ, $dimension] as $value) {
            if ($value < -0x80000000 || $value > 0x7fffffff) {
                throw new InvalidValueException('Level-chunk integer must fit in 32 bits.');
            }
        }
        if ($subChunkCount < 0 || $subChunkCount > self::MAX_SUB_CHUNK_COUNT) {
            throw new InvalidValueException('Sub-chunk count exceeds the bounded MVP range.');
        }
        if ($subChunkLimit < 0 || $subChunkLimit > self::MAX_SUB_CHUNK_COUNT
            || (!$requestSubChunks && $subChunkLimit !== 0)) {
            throw new InvalidValueException('Level-chunk request limit is invalid.');
        }
        if (strlen($data) > CodecSupport::MAX_CHUNK_BYTES) {
            throw new InvalidValueException('Chunk data exceeds its byte limit.');
        }
    }

    public function packetId(): int { return PacketIds::LEVEL_CHUNK; }

    /**
     * Produces bedrock/two-dirt/grass at y=60..63 and Bedrock's 24 overworld biome palettes.
     *
     * @param array{air: int, bedrock: int, dirt: int, grass_block: int} $runtimeIds
     */
    public static function fixedFlat(int $chunkX, int $chunkZ, array $runtimeIds, int $biomeId): self
    {
        $runtimeIdKeys = array_keys($runtimeIds);
        sort($runtimeIdKeys);
        if ($runtimeIdKeys !== ['air', 'bedrock', 'dirt', 'grass_block']
            || $biomeId < 0 || $biomeId > 65_535 || count(array_unique($runtimeIds)) !== 4) {
            throw new InvalidValueException('Flat chunk biome and block runtime IDs are invalid.');
        }
        foreach ($runtimeIds as $runtimeId) {
            if ($runtimeId < -0x80000000 || $runtimeId > 0x7fffffff) {
                throw new InvalidValueException('Flat chunk block runtime ID is outside the signed-varint range.');
            }
        }

        $sections = [];
        for ($subChunkY = self::OVERWORLD_MIN_SECTION_Y; $subChunkY < self::FIXED_FLAT_SECTION_Y; ++$subChunkY) {
            $sections[] = ChunkSectionData::allAir($subChunkY, $runtimeIds['air']);
        }
        $surface = array_fill(0, ChunkSectionData::CELL_COUNT, $runtimeIds['air']);
        for ($x = 0; $x < 16; ++$x) {
            for ($z = 0; $z < 16; ++$z) {
                $base = ($x << 8) | ($z << 4);
                $surface[$base | 12] = $runtimeIds['bedrock'];
                $surface[$base | 13] = $runtimeIds['dirt'];
                $surface[$base | 14] = $runtimeIds['dirt'];
                $surface[$base | 15] = $runtimeIds['grass_block'];
            }
        }
        $sections[] = ChunkSectionData::fromRuntimeIds(self::FIXED_FLAT_SECTION_Y, array_values($surface));
        // Use the conservative V2 paletted-storage representation used by the qualified
        // full-column path: 2 bits per entry, 256 zero words, then one runtime-ID entry.
        // Although a V0 singleton palette is compact, this shape is accepted by the retail
        // client on the same full-column path used for the block sub-chunks.
        $biomes = array_fill(0, self::OVERWORLD_SECTION_COUNT,
            PalettedStorage::singleton($biomeId, ChunkColumnData::BIOME_CELL_COUNT, 2));
        return ChunkSerializer::fullColumn(new ChunkColumnData(
            $chunkX,
            $chunkZ,
            0,
            self::OVERWORLD_MIN_SECTION_Y,
            self::OVERWORLD_MAX_SECTION_Y,
            $sections,
            $biomes,
        ));
    }

    /** Produces a biome shell which asks the client to request through the highest non-empty section. */
    public static function fixedFlatShell(int $chunkX, int $chunkZ, int $biomeId): self
    {
        if ($biomeId < 0 || $biomeId > 65_535) {
            throw new InvalidValueException('Flat chunk biome runtime ID is invalid.');
        }
        $biome = "\x01" . CodecSupport::writer()->writeSignedVarInt($biomeId)->toString();
        // Bedrock clients cannot safely copy-last biome zero (ocean), so retain full
        // singleton storages for that valid edge case.
        $biomes = $biome . ($biomeId === 0 ? str_repeat($biome, self::OVERWORLD_SECTION_COUNT - 1)
            : str_repeat("\xff", self::OVERWORLD_SECTION_COUNT - 1));
        return new self($chunkX, $chunkZ, 0, 0, $biomes . "\x00", true, self::FIXED_FLAT_SUB_CHUNK_LIMIT);
    }

    public function encode(): string
    {
        return CodecSupport::writer()
            ->writeSignedVarInt($this->chunkX)
            ->writeSignedVarInt($this->chunkZ)
            ->writeSignedVarInt($this->dimension)
            ->writeUnsignedVarInt($this->subChunkCount)
            ->writeUnsignedByte($this->requestSubChunks ? 1 : 0)
            ->writeBytes($this->requestSubChunks ? CodecSupport::writer()->writeSignedVarInt($this->subChunkLimit)->toString() : '')
            ->writeUnsignedByte(0) // cache disabled
            ->writeUnsignedVarInt(0) // empty cache metadata
            ->writeUnsignedVarInt(strlen($this->data))
            ->writeBytes($this->data)
            ->toString();
    }

    public static function decode(string $bytes): self
    {
        $x = CodecSupport::reader($bytes)->readSignedVarInt();
        $z = $x->reader->readSignedVarInt();
        $dimension = $z->reader->readSignedVarInt();
        $count = $dimension->reader->readUnsignedVarInt();
        if ($count->value > self::MAX_SUB_CHUNK_COUNT) {
            throw new MalformedDataException('Sub-chunk count exceeds the bounded MVP range.');
        }
        [$hasRequestLimit, $reader] = CodecSupport::readBoolean($count->reader);
        $subChunkLimit = 0;
        if ($hasRequestLimit) {
            $limit = $reader->readSignedVarInt();
            if ($limit->value < 0 || $limit->value > self::MAX_SUB_CHUNK_COUNT) {
                throw new MalformedDataException('Level-chunk request limit is invalid.');
            }
            $subChunkLimit = $limit->value;
            $reader = $limit->reader;
        }
        [$caching, $reader] = CodecSupport::readBoolean($reader);
        if ($caching) {
            throw new MalformedDataException('Chunk-cache blobs are outside the MVP codec slice.');
        }
        $cacheMetadataCount = $reader->readUnsignedVarInt();
        if ($cacheMetadataCount->value > self::MAX_CACHE_METADATA_COUNT) {
            throw new MalformedDataException('Level-chunk cache metadata exceeds its count limit.');
        }
        if ($cacheMetadataCount->value !== 0) {
            throw new MalformedDataException('Chunk-cache metadata is outside the MVP codec slice.');
        }
        $length = $cacheMetadataCount->reader->readUnsignedVarInt();
        if ($length->value > CodecSupport::MAX_CHUNK_BYTES) {
            throw new MalformedDataException('Chunk data exceeds its byte limit.');
        }
        $data = $length->reader->readBytes($length->value);
        CodecSupport::requireEnd($data->reader);
        return new self($x->value, $z->value, $dimension->value, $count->value, $data->value, $hasRequestLimit, $subChunkLimit);
    }
}
