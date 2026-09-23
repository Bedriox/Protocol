<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

use Bedriox\Protocol\Codec\ByteBufferWriter;
use Bedriox\Protocol\Exception\InvalidValueException;

/** Current Bedrock v9 subchunk and full-column serializer. */
final class ChunkSerializer
{
    private const array BITS_PER_ENTRY = [1, 2, 3, 4, 5, 6, 8, 16];

    private function __construct()
    {
    }

    public static function fullColumn(ChunkColumnData $column): LevelChunkPacket
    {
        $writer = CodecSupport::writer();
        foreach ($column->sections as $section) {
            $writer = $writer->writeBytes(self::section($section));
        }
        $previousBiome = null;
        foreach ($column->biomes as $biome) {
            if ($previousBiome !== null && $biome->palette[0] !== 0 && $previousBiome->palette[0] !== 0
                && self::storagesEqual($biome, $previousBiome)) {
                $writer = $writer->writeUnsignedByte(0xff);
            } else {
                $writer = $writer->writeBytes(self::storage($biome));
            }
            $previousBiome = $biome;
        }
        $writer = $writer->writeUnsignedByte(0);
        foreach ($column->blockEntityNbt as $blockEntity) {
            $writer = $writer->writeBytes($blockEntity);
        }
        $data = $writer->toString();
        if (strlen($data) > CodecSupport::MAX_CHUNK_BYTES) {
            throw new InvalidValueException('Serialized chunk column exceeds its byte limit.');
        }
        return new LevelChunkPacket(
            $column->chunkX,
            $column->chunkZ,
            $column->dimension,
            count($column->sections),
            $data,
        );
    }

    public static function section(ChunkSectionData $section): string
    {
        $writer = CodecSupport::writer()->writeUnsignedByte(9)
            ->writeUnsignedByte(count($section->blockLayers))->writeUnsignedByte($section->sectionY & 0xff);
        foreach ($section->blockLayers as $layer) {
            $writer = $writer->writeBytes(self::storage($layer));
        }
        return $writer->toString();
    }

    public static function storage(PalettedStorage|PackedPalettedStorage $storage): string
    {
        if ($storage instanceof PackedPalettedStorage) {
            if ($storage->bitsPerEntry === 0) {
                return CodecSupport::writer()->writeUnsignedByte(1)
                    ->writeSignedVarInt($storage->palette[0])->toString();
            }
            $writer = CodecSupport::writer()->writeUnsignedByte(($storage->bitsPerEntry << 1) | 1)
                ->writeBytes($storage->wordArray)
                ->writeSignedVarInt(count($storage->palette));
            foreach ($storage->palette as $runtimeId) {
                $writer = $writer->writeSignedVarInt($runtimeId);
            }

            return $writer->toString();
        }
        $paletteSize = count($storage->palette);
        if ($paletteSize === 1 && $storage->minimumBitsPerEntry === 0) {
            return CodecSupport::writer()->writeUnsignedByte(1)
                ->writeSignedVarInt($storage->palette[0])->toString();
        }
        $bits = max($storage->minimumBitsPerEntry, self::bitsForPalette($paletteSize));
        $entriesPerWord = intdiv(32, $bits);
        $wordCount = intdiv($storage->size() + $entriesPerWord - 1, $entriesPerWord);
        $writer = CodecSupport::writer()->writeUnsignedByte(($bits << 1) | 1);
        for ($wordIndex = 0; $wordIndex < $wordCount; ++$wordIndex) {
            $word = 0;
            $firstEntry = $wordIndex * $entriesPerWord;
            for ($entry = 0; $entry < $entriesPerWord; ++$entry) {
                $cell = $firstEntry + $entry;
                if ($cell >= $storage->size()) {
                    break;
                }
                $word |= $storage->indices[$cell] << ($entry * $bits);
            }
            $writer = $writer->writeUnsignedIntLE($word & 0xffffffff);
        }
        $writer = $writer->writeSignedVarInt($paletteSize);
        foreach ($storage->palette as $runtimeId) {
            $writer = $writer->writeSignedVarInt($runtimeId);
        }
        return $writer->toString();
    }

    private static function bitsForPalette(int $paletteSize): int
    {
        foreach (self::BITS_PER_ENTRY as $bits) {
            if ($paletteSize <= (1 << $bits)) {
                return $bits;
            }
        }
        throw new InvalidValueException('Paletted storage has too many entries for Bedrock bit packing.');
    }

    private static function storagesEqual(
        PalettedStorage|PackedPalettedStorage $first,
        PalettedStorage|PackedPalettedStorage $second,
    ): bool {
        if ($first->palette !== $second->palette || $first::class !== $second::class) {
            return false;
        }
        if ($first instanceof PackedPalettedStorage && $second instanceof PackedPalettedStorage) {
            return $first->bitsPerEntry === $second->bitsPerEntry
                && $first->entryCount === $second->entryCount
                && hash_equals($first->wordArray, $second->wordArray);
        }

        return $first instanceof PalettedStorage && $second instanceof PalettedStorage
            && $first->minimumBitsPerEntry === $second->minimumBitsPerEntry
            && $first->indices === $second->indices;
    }
}
