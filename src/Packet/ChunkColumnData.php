<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

use Bedriox\Protocol\Exception\InvalidValueException;

/** Immutable generic input for one non-cache full-column LevelChunk payload. */
final readonly class ChunkColumnData
{
    public const int BIOME_CELL_COUNT = 16 * 16 * 16;
    private const int MAX_SECTIONS = 64;
    private const int MAX_BLOCK_ENTITIES = 1_024;
    private const int MAX_BLOCK_ENTITY_BYTES = 1_048_576;

    /**
     * @param list<ChunkSectionData> $sections Contiguous from minSectionY through the highest included section.
     * @param list<PalettedStorage> $biomes One 4,096-cell storage for every dimension section.
     * @param list<string> $blockEntityNbt Concatenated network-NBT compound roots.
     * @param list<int> $borderBlocks Reserved Education entries; non-empty input is rejected for retail safety.
     */
    public function __construct(
        public int $chunkX,
        public int $chunkZ,
        public int $dimension,
        public int $minSectionY,
        public int $maxSectionY,
        public array $sections,
        public array $biomes,
        public array $blockEntityNbt = [],
        public array $borderBlocks = [],
    ) {
        foreach ([$chunkX, $chunkZ, $dimension] as $value) {
            if ($value < -0x80000000 || $value > 0x7fffffff) {
                throw new InvalidValueException('Chunk column coordinate or dimension must fit a signed 32-bit integer.');
            }
        }
        $sectionCount = $maxSectionY - $minSectionY + 1;
        if ($minSectionY < -128 || $maxSectionY > 127 || $sectionCount < 1 || $sectionCount > self::MAX_SECTIONS) {
            throw new InvalidValueException('Chunk column section bounds are invalid.');
        }
        if (!array_is_list($sections) || count($sections) > $sectionCount) {
            throw new InvalidValueException('Chunk column sections must be a bounded list.');
        }
        foreach ($sections as $index => $section) {
            if (!$section instanceof ChunkSectionData || $section->sectionY !== $minSectionY + $index) {
                throw new InvalidValueException('Chunk column sections must be contiguous from the minimum section Y.');
            }
        }
        if (!array_is_list($biomes) || count($biomes) !== $sectionCount) {
            throw new InvalidValueException('Chunk column requires one biome storage per dimension section.');
        }
        foreach ($biomes as $biome) {
            if (!self::isValidBiome($biome)) {
                throw new InvalidValueException('Chunk column contains an invalid biome storage.');
            }
        }
        if (!array_is_list($borderBlocks) || $borderBlocks !== []) {
            throw new InvalidValueException('Non-empty border-block arrays are unsafe for retail Bedrock clients.');
        }
        if (!array_is_list($blockEntityNbt) || count($blockEntityNbt) > self::MAX_BLOCK_ENTITIES) {
            throw new InvalidValueException('Chunk column block-entity count exceeds its limit.');
        }
        $totalBlockEntityBytes = 0;
        foreach ($blockEntityNbt as $nbt) {
            if (!self::isValidBlockEntity($nbt)) {
                throw new InvalidValueException('Chunk block entity must be a bounded network-NBT compound root.');
            }
            $length = strlen($nbt);
            $totalBlockEntityBytes += $length;
            if ($totalBlockEntityBytes > CodecSupport::MAX_CHUNK_BYTES) {
                throw new InvalidValueException('Chunk block-entity data exceeds the chunk byte limit.');
            }
        }
    }

    private static function isValidBiome(mixed $biome): bool
    {
        return $biome instanceof PalettedStorage && $biome->size() === self::BIOME_CELL_COUNT;
    }

    private static function isValidBlockEntity(mixed $nbt): bool
    {
        return is_string($nbt) && strlen($nbt) >= 3 && strlen($nbt) <= self::MAX_BLOCK_ENTITY_BYTES
            && $nbt[0] === "\x0a";
    }
}
