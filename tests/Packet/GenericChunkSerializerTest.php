<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Tests\Packet;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Bedriox\Protocol\Codec\ByteBufferReader;
use Bedriox\Protocol\Exception\InvalidValueException;
use Bedriox\Protocol\Packet\ChunkColumnData;
use Bedriox\Protocol\Packet\ChunkSectionData;
use Bedriox\Protocol\Packet\ChunkSerializer;
use Bedriox\Protocol\Packet\LevelChunkPacket;
use Bedriox\Protocol\Packet\PalettedStorage;

final class GenericChunkSerializerTest extends TestCase
{
    public function testNegativeColumnCoordinatesAndSignedSectionYRoundTrip(): void
    {
        $air = PalettedStorage::singleton(17_025, ChunkSectionData::CELL_COUNT);
        $section = new ChunkSectionData(-4, [$air]);
        $biomes = [
            PalettedStorage::singleton(1, ChunkColumnData::BIOME_CELL_COUNT),
            PalettedStorage::singleton(2, ChunkColumnData::BIOME_CELL_COUNT),
        ];

        $packet = ChunkSerializer::fullColumn(new ChunkColumnData(-17, -33, 0, -4, -3, [$section], $biomes));
        $decoded = LevelChunkPacket::decode($packet->encode());

        self::assertSame(-17, $decoded->chunkX);
        self::assertSame(-33, $decoded->chunkZ);
        self::assertSame(1, $decoded->subChunkCount);
        self::assertSame('0901fc01', bin2hex(substr($decoded->data, 0, 4)));
        self::assertSame(17_025, ByteBufferReader::fromString(substr($decoded->data, 4), 16)->readSignedVarInt()->value);
    }

    /** @return iterable<string, array{int, int}> */
    public static function paletteWidths(): iterable
    {
        yield 'one bit' => [2, 1];
        yield 'two bits' => [3, 2];
        yield 'three padded bits' => [5, 3];
        yield 'four bits' => [9, 4];
        yield 'five padded bits' => [17, 5];
        yield 'six padded bits' => [33, 6];
        yield 'eight bits' => [65, 8];
        yield 'sixteen bits' => [257, 16];
        yield 'sixteen bits maximum palette' => [4_096, 16];
    }

    #[DataProvider('paletteWidths')]
    public function testEveryBedrockPaletteWidthReconstructsEveryCell(int $paletteSize, int $expectedBits): void
    {
        $palette = range(10_000, 10_000 + $paletteSize - 1);
        $indices = [];
        for ($cell = 0; $cell < ChunkSectionData::CELL_COUNT; ++$cell) {
            $indices[] = ($cell * 17 + 3) % $paletteSize;
        }
        $wire = ChunkSerializer::storage(new PalettedStorage($palette, $indices));
        $reader = ByteBufferReader::fromString($wire, 32_768);
        $header = $reader->readUnsignedByte();
        self::assertSame(($expectedBits << 1) | 1, $header->value);
        $entriesPerWord = intdiv(32, $expectedBits);
        $wordCount = intdiv(count($indices) + $entriesPerWord - 1, $entriesPerWord);
        $words = $header->reader->readBytes($wordCount * 4);
        $paletteCount = $words->reader->readSignedVarInt();
        self::assertSame($paletteSize, $paletteCount->value);
        $reader = $paletteCount->reader;
        foreach ($palette as $runtimeId) {
            $entry = $reader->readSignedVarInt();
            self::assertSame($runtimeId, $entry->value);
            $reader = $entry->reader;
        }
        self::assertTrue($reader->isAtEnd());

        for ($cell = 0; $cell < count($indices); ++$cell) {
            $wordOffset = intdiv($cell, $entriesPerWord) * 4;
            $decoded = unpack('Vvalue', substr($words->value, $wordOffset, 4));
            self::assertIsArray($decoded);
            self::assertIsInt($decoded['value']);
            $word = $decoded['value'];
            $shift = ($cell % $entriesPerWord) * $expectedBits;
            $mask = (1 << $expectedBits) - 1;
            self::assertSame($indices[$cell], ($word >> $shift) & $mask);
        }
    }

    public function testMultipleLayersBiomesBorderAndBlockEntitiesHaveExactFraming(): void
    {
        $layerZero = PalettedStorage::singleton(7, ChunkSectionData::CELL_COUNT);
        $layerOne = PalettedStorage::singleton(8, ChunkSectionData::CELL_COUNT);
        $biome = PalettedStorage::singleton(4, ChunkColumnData::BIOME_CELL_COUNT);
        $blockEntities = ["\x0a\x00\x00", "\x0a\x00\x00"];
        $packet = ChunkSerializer::fullColumn(new ChunkColumnData(
            2,
            -3,
            7,
            -1,
            1,
            [new ChunkSectionData(-1, [$layerZero, $layerOne])],
            [$biome, $biome, $biome],
            $blockEntities,
        ));

        self::assertSame(1, $packet->subChunkCount);
        self::assertStringStartsWith("\x09\x02\xff\x01\x0e\x01\x10", $packet->data);
        self::assertStringContainsString("\x01\x08\xff\xff\x00", $packet->data);
        self::assertStringEndsWith("\x00" . implode('', $blockEntities), $packet->data);
    }

    public function testBiomeZeroNeverUsesCopyLastAndMinimumWidthIsHonoured(): void
    {
        $biome = PalettedStorage::singleton(0, ChunkColumnData::BIOME_CELL_COUNT, 2);
        $packet = ChunkSerializer::fullColumn(new ChunkColumnData(0, 0, 0, 0, 1, [], [$biome, $biome]));
        $oneStorageLength = 1 + (256 * 4) + 1 + 1;

        self::assertSame(5, ord($packet->data[0]));
        self::assertSame(5, ord($packet->data[$oneStorageLength]));
        self::assertSame(0, ord($packet->data[$oneStorageLength * 2]));
    }

    /** @return iterable<string, array{callable(): mixed}> */
    public static function invalidInputs(): iterable
    {
        yield 'duplicate palette' => [static fn () => new PalettedStorage([1, 1], [0])];
        yield 'non-integer palette' => [static fn () => (new \ReflectionClass(PalettedStorage::class))
            ->newInstanceArgs([['1'], [0]])];
        yield 'non-integer index' => [static fn () => (new \ReflectionClass(PalettedStorage::class))
            ->newInstanceArgs([[1], ['0']])];
        yield 'palette index overflow' => [static fn () => new PalettedStorage([1], [1])];
        yield 'bad minimum bits' => [static fn () => new PalettedStorage([1], [0], 7)];
        yield 'wrong value count' => [static fn () => PalettedStorage::fromValues([1], 2)];
        yield 'section Y overflow' => [static fn () => ChunkSectionData::allAir(128, 1)];
        yield 'empty layers' => [static fn () => new ChunkSectionData(0, [])];
        yield 'too many layers' => [static fn () => new ChunkSectionData(0,
            array_fill(0, 9, PalettedStorage::singleton(1, ChunkSectionData::CELL_COUNT)))];
        yield 'gapped sections' => [static fn () => new ChunkColumnData(0, 0, 0, -4, -3,
            [ChunkSectionData::allAir(-3, 1)], self::biomes(2))];
        yield 'wrong biome count' => [static fn () => new ChunkColumnData(0, 0, 0, 0, 1, [], self::biomes(1))];
        yield 'too many dimension sections' => [static fn () => new ChunkColumnData(0, 0, 0, 0, 64, [], self::biomes(65))];
        yield 'nonempty retail border blocks' => [static fn () => new ChunkColumnData(0, 0, 0, 0, 0, [], self::biomes(1), [], [1])];
        yield 'non-compound block entity' => [static fn () => new ChunkColumnData(0, 0, 0, 0, 0, [], self::biomes(1), ["\x09\x00"])];
        yield 'too many block entities' => [static fn () => new ChunkColumnData(0, 0, 0, 0, 0, [], self::biomes(1),
            array_fill(0, 1_025, "\x0a\x00"))];
    }

    #[DataProvider('invalidInputs')]
    /** @param callable(): mixed $factory */
    public function testGenericInputsAreBounded(callable $factory): void
    {
        $this->expectException(InvalidValueException::class);
        $factory();
    }

    /** @return list<PalettedStorage> */
    private static function biomes(int $count): array
    {
        return array_fill(0, $count, PalettedStorage::singleton(1, ChunkColumnData::BIOME_CELL_COUNT));
    }
}
