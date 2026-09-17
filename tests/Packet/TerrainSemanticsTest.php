<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Tests\Packet;

use PHPUnit\Framework\TestCase;
use Bedriox\Protocol\Codec\ByteBufferReader;
use Bedriox\Protocol\Packet\LevelChunkPacket;
use Bedriox\Protocol\Packet\SubChunkPacket;
use Bedriox\Protocol\Packet\SubChunkRequestPacket;
use Bedriox\Protocol\Packet\SubChunkResponse;

final class TerrainSemanticsTest extends TestCase
{
    /** @var array{air: int, bedrock: int, dirt: int, grass_block: int} */
    private const array IDS = ['air' => 17_025, 'bedrock' => 17_901, 'dirt' => 13_456, 'grass_block' => 14_944];

    public function testBiomeShellUsesOnePaletteThenCopyLastAndDerivedLimit(): void
    {
        $shell = LevelChunkPacket::fixedFlatShell(-2, 4, 1);

        self::assertSame(LevelChunkPacket::OVERWORLD_SECTION_COUNT, 24);
        self::assertSame(8, LevelChunkPacket::FIXED_FLAT_SUB_CHUNK_LIMIT);
        self::assertSame(LevelChunkPacket::FIXED_FLAT_SUB_CHUNK_LIMIT, $shell->subChunkLimit);
        self::assertSame("\x01\x02" . str_repeat("\xff", 23) . "\x00", $shell->data);
        self::assertEquals($shell, LevelChunkPacket::decode($shell->encode()));

        $ocean = LevelChunkPacket::fixedFlatShell(0, 0, 0);
        self::assertSame(str_repeat("\x01\x00", 24) . "\x00", $ocean->data);
    }

    public function testRequestedSectionReconstructsEveryFixedFlatCell(): void
    {
        $request = new SubChunkRequestPacket(0, 0, 3, 0, [['x' => 0, 'y' => 0, 'z' => 0]]);
        $packet = SubChunkPacket::fixedFlat($request, self::IDS, 1);
        $response = $packet->responses[0];

        self::assertSame(SubChunkResponse::SUCCESS, $response->result);
        self::assertNotNull($response->data);
        $blocks = self::decodeV9Section($response->data);
        self::assertCount(4_096, $blocks);

        for ($x = 0; $x < 16; ++$x) {
            for ($z = 0; $z < 16; ++$z) {
                for ($localY = 0; $localY < 16; ++$localY) {
                    $expected = match ($localY) {
                        12 => self::IDS['bedrock'],
                        13, 14 => self::IDS['dirt'],
                        15 => self::IDS['grass_block'],
                        default => self::IDS['air'],
                    };
                    self::assertSame($expected, $blocks[($x << 8) | ($z << 4) | $localY]);
                }
            }
        }
    }

    public function testFullColumnPayloadHasValidAirLayersTerrainBiomesAndBorderFraming(): void
    {
        $chunk = LevelChunkPacket::fixedFlat(-2, 4, self::IDS, 1);
        $reader = ByteBufferReader::fromString($chunk->data, 16_384);

        self::assertSame(8, $chunk->subChunkCount);
        for ($sectionY = -4; $sectionY <= 3; ++$sectionY) {
            $version = $reader->readUnsignedByte();
            $layers = $version->reader->readUnsignedByte();
            $encodedY = $layers->reader->readUnsignedByte();
            self::assertSame(9, $version->value);
            self::assertGreaterThanOrEqual(1, $layers->value);
            self::assertSame($sectionY & 0xff, $encodedY->value);
            self::assertSame(1, $layers->value);

            $header = $encodedY->reader->readUnsignedByte();
            self::assertSame(1, $header->value & 1, 'Block palettes must contain network runtime IDs.');
            $bitsPerBlock = $header->value >> 1;
            if ($sectionY < 3) {
                self::assertSame(0, $bitsPerBlock);
                $air = $header->reader->readSignedVarInt();
                self::assertSame(self::IDS['air'], $air->value);
                $reader = $air->reader;
                continue;
            }

            self::assertSame(2, $bitsPerBlock);
            $words = $header->reader->readBytes(256 * 4);
            $paletteCount = $words->reader->readSignedVarInt();
            self::assertSame(4, $paletteCount->value);
            $reader = $paletteCount->reader;
            $palette = [];
            for ($index = 0; $index < $paletteCount->value; ++$index) {
                $entry = $reader->readSignedVarInt();
                $palette[] = $entry->value;
                $reader = $entry->reader;
            }
            self::assertSame(array_values(self::IDS), $palette);

            for ($wordIndex = 0; $wordIndex < 256; ++$wordIndex) {
                $decoded = unpack('Vvalue', substr($words->value, $wordIndex * 4, 4));
                self::assertIsArray($decoded);
                self::assertSame(0xe9000000, $decoded['value']);
            }
        }

        for ($section = 0; $section < LevelChunkPacket::OVERWORLD_SECTION_COUNT; ++$section) {
            $header = $reader->readUnsignedByte();
            if ($section > 0) {
                self::assertSame(0xff, $header->value, 'Repeated biome sections must copy the preceding palette.');
                $reader = $header->reader;
                continue;
            }
            self::assertSame(5, $header->value);
            $words = $header->reader->readBytes(256 * 4);
            self::assertSame(str_repeat("\x00", 256 * 4), $words->value);
            $paletteCount = $words->reader->readSignedVarInt();
            $biome = $paletteCount->reader->readSignedVarInt();
            self::assertSame(1, $paletteCount->value);
            self::assertSame(1, $biome->value);
            $reader = $biome->reader;
        }

        $borderCount = $reader->readUnsignedByte();
        self::assertSame(0, $borderCount->value);
        self::assertTrue($borderCount->reader->isAtEnd(), 'No block-entity bytes should follow the border count.');
    }

    public function testLevelChunkRejectsCountsBeyondOfficialBounds(): void
    {
        $this->expectException(\Bedriox\Protocol\Exception\InvalidValueException::class);
        new LevelChunkPacket(0, 0, 0, 65, '');
    }

    public function testCurrentAndRenderHeightMapsUseLatestChunkedRepresentation(): void
    {
        $request = new SubChunkRequestPacket(0, 0, 3, 0, [['x' => 0, 'y' => 0, 'z' => 0]]);
        $packet = SubChunkPacket::fixedFlat($request, self::IDS, 1);
        $reader = ByteBufferReader::fromString($packet->encode(), 8_192);

        $cache = $reader->readUnsignedByte();
        $dimension = $cache->reader->readSignedVarInt();
        $center = $dimension->reader->readBytes(12);
        $count = $center->reader->readUnsignedVarInt();
        $prefix = $count->reader->readBytes(5); // offsets, result, data-present
        $dataLength = $prefix->reader->readUnsignedVarInt();
        $data = $dataLength->reader->readBytes($dataLength->value);
        self::assertSame(0, $cache->value);
        self::assertSame(0, $dimension->value);
        self::assertSame(1, $count->value);

        $reader = $data->reader;
        foreach (['current', 'render'] as $name) {
            $type = $reader->readUnsignedByte();
            $present = $type->reader->readUnsignedByte();
            self::assertSame(SubChunkResponse::HEIGHT_DATA, $type->value, $name);
            self::assertSame(1, $present->value, $name);
            $reader = $present->reader;
            $map = '';
            for ($piece = 0; $piece < 16; ++$piece) {
                $length = $reader->readUnsignedVarInt();
                self::assertSame(16, $length->value, $name);
                $bytes = $length->reader->readBytes(16);
                $map .= $bytes->value;
                $reader = $bytes->reader;
            }
            self::assertSame(str_repeat("\x0f", 256), $map, $name);
        }
        $blob = $reader->readUnsignedByte();
        self::assertSame(0, $blob->value);
        self::assertTrue($blob->reader->isAtEnd());
    }

    public function testEveryVerticalAndHorizontalResultClassIsExplicit(): void
    {
        $request = new SubChunkRequestPacket(0, 0, 3, 0, [
            ['x' => 0, 'y' => -8, 'z' => 0],
            ['x' => 0, 'y' => -1, 'z' => 0],
            ['x' => 0, 'y' => 1, 'z' => 0],
            ['x' => 0, 'y' => 20, 'z' => 0],
            ['x' => 2, 'y' => 0, 'z' => 0],
        ]);
        $responses = SubChunkPacket::fixedFlat($request, self::IDS, 1)->responses;

        self::assertSame(SubChunkResponse::INDEX_OUT_OF_BOUNDS, $responses[0]->result);
        self::assertSame(SubChunkResponse::SUCCESS_ALL_AIR, $responses[1]->result);
        self::assertSame(SubChunkResponse::HEIGHT_TOO_HIGH, $responses[1]->heightMapType);
        self::assertSame(SubChunkResponse::SUCCESS_ALL_AIR, $responses[2]->result);
        self::assertSame(SubChunkResponse::HEIGHT_TOO_LOW, $responses[2]->heightMapType);
        self::assertSame(SubChunkResponse::INDEX_OUT_OF_BOUNDS, $responses[3]->result);
        self::assertSame(SubChunkResponse::CHUNK_NOT_FOUND, $responses[4]->result);
    }

    /** @return list<int> */
    private static function decodeV9Section(string $data): array
    {
        $reader = ByteBufferReader::fromString($data, 2_048);
        $version = $reader->readUnsignedByte();
        $layers = $version->reader->readUnsignedByte();
        $sectionY = $layers->reader->readUnsignedByte();
        $header = $sectionY->reader->readUnsignedByte();
        self::assertSame(9, $version->value);
        self::assertSame(1, $layers->value);
        self::assertSame(3, $sectionY->value);
        self::assertSame(5, $header->value); // two bits per value, runtime palette

        $words = $header->reader->readBytes(256 * 4);
        $paletteCount = $words->reader->readSignedVarInt();
        self::assertSame(4, $paletteCount->value);
        $reader = $paletteCount->reader;
        $palette = [];
        for ($index = 0; $index < $paletteCount->value; ++$index) {
            $entry = $reader->readSignedVarInt();
            $palette[] = $entry->value;
            $reader = $entry->reader;
        }
        self::assertSame(array_values(self::IDS), $palette);
        self::assertTrue($reader->isAtEnd());

        /** @var list<int> $blocks */
        $blocks = [];
        for ($wordIndex = 0; $wordIndex < 256; ++$wordIndex) {
            $decoded = unpack('Vvalue', substr($words->value, $wordIndex * 4, 4));
            self::assertIsArray($decoded);
            $word = $decoded['value'];
            self::assertIsInt($word);
            for ($cell = 0; $cell < 16; ++$cell) {
                $blocks[] = $palette[($word >> ($cell * 2)) & 3];
            }
        }
        return $blocks;
    }
}
