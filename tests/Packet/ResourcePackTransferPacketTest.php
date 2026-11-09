<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Tests\Packet;

use Bedriox\Protocol\Exception\CodecException;
use Bedriox\Protocol\Exception\InvalidValueException;
use Bedriox\Protocol\Packet\BedrockPacketCodec;
use Bedriox\Protocol\Packet\CodecSupport;
use Bedriox\Protocol\Packet\Packet;
use Bedriox\Protocol\Packet\PacketIds;
use Bedriox\Protocol\Packet\ResourcePackChunkDataPacket;
use Bedriox\Protocol\Packet\ResourcePackChunkRequestPacket;
use Bedriox\Protocol\Packet\ResourcePackDataInfoPacket;
use Bedriox\Protocol\Packet\ResourcePackType;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ResourcePackTransferPacketTest extends TestCase
{
    private const string PACK_ID = '00112233-4455-6677-8899-aabbccddeeff';
    private const string PACK_VERSION = '1.2.3';

    /** @return iterable<string, array{Packet, string}> */
    public static function vectors(): iterable
    {
        $identity = "\x2a" . self::PACK_ID . '_' . self::PACK_VERSION;
        $hash = hex2bin('000102030405060708090a0b0c0d0e0f101112131415161718191a1b1c1d1e1f');
        self::assertIsString($hash);

        yield 'data information' => [
            new ResourcePackDataInfoPacket(
                self::PACK_ID,
                self::PACK_VERSION,
                262_144,
                3,
                600_000,
                $hash,
                true,
                ResourcePackType::Resources,
            ),
            $identity
                . "\x00\x00\x04\x00"
                . "\x03\x00\x00\x00"
                . "\xc0\x27\x09\x00\x00\x00\x00\x00"
                . "\x20" . $hash . "\x01\x06",
        ];
        yield 'chunk data' => [
            new ResourcePackChunkDataPacket(self::PACK_ID, self::PACK_VERSION, 2, 524_288, "\xde\xad\xbe\xef"),
            $identity
                . "\x02\x00\x00\x00"
                . "\x00\x00\x08\x00\x00\x00\x00\x00"
                . "\x04\xde\xad\xbe\xef",
        ];
        yield 'chunk request' => [
            new ResourcePackChunkRequestPacket(self::PACK_ID, self::PACK_VERSION, 2),
            $identity . "\x02\x00\x00\x00",
        ];
    }

    #[DataProvider('vectors')]
    public function testLiteralVectorsAndRegistry(Packet $packet, string $wire): void
    {
        self::assertSame($wire, BedrockPacketCodec::encode($packet));
        self::assertEquals($packet, BedrockPacketCodec::decode($packet->packetId(), $wire));
        self::assertSame($packet->packetId(), BedrockPacketCodec::packetId($packet));
        self::assertTrue(BedrockPacketCodec::supportsDecoding($packet->packetId()));
    }

    #[DataProvider('vectors')]
    public function testEveryTruncationAndTrailingByteIsRejected(Packet $packet, string $wire): void
    {
        for ($length = 0; $length < strlen($wire); ++$length) {
            try {
                BedrockPacketCodec::decode($packet->packetId(), substr($wire, 0, $length));
                self::fail("Truncation at {$length} bytes was accepted for packet {$packet->packetId()}.");
            } catch (CodecException) {
                self::addToAssertionCount(1);
            }
        }

        $this->expectException(CodecException::class);
        BedrockPacketCodec::decode($packet->packetId(), $wire . "\0");
    }

    public function testPacketIdsAndResourcePackTypeDomainAreExact(): void
    {
        self::assertSame(82, PacketIds::RESOURCE_PACK_DATA_INFO);
        self::assertSame(83, PacketIds::RESOURCE_PACK_CHUNK_DATA);
        self::assertSame(84, PacketIds::RESOURCE_PACK_CHUNK_REQUEST);
        self::assertSame(range(0, 8), array_map(
            static fn (ResourcePackType $type): int => $type->value,
            ResourcePackType::cases(),
        ));
    }

    public function testCallerBoundsAreEnforced(): void
    {
        $hash = str_repeat('h', CodecSupport::RESOURCE_PACK_HASH_BYTES);
        $operations = [
            static fn (): ResourcePackDataInfoPacket => new ResourcePackDataInfoPacket(
                self::PACK_ID,
                '',
                1,
                1,
                1,
                $hash,
                false,
                ResourcePackType::Resources,
            ),
            static fn (): ResourcePackDataInfoPacket => new ResourcePackDataInfoPacket(
                self::PACK_ID,
                self::PACK_VERSION,
                0,
                1,
                1,
                $hash,
                false,
                ResourcePackType::Resources,
            ),
            static fn (): ResourcePackDataInfoPacket => new ResourcePackDataInfoPacket(
                self::PACK_ID,
                self::PACK_VERSION,
                1,
                CodecSupport::MAX_RESOURCE_PACK_CHUNKS + 1,
                1,
                $hash,
                false,
                ResourcePackType::Resources,
            ),
            static fn (): ResourcePackDataInfoPacket => new ResourcePackDataInfoPacket(
                self::PACK_ID,
                self::PACK_VERSION,
                1,
                1,
                -1,
                $hash,
                false,
                ResourcePackType::Resources,
            ),
            static fn (): ResourcePackDataInfoPacket => new ResourcePackDataInfoPacket(
                self::PACK_ID,
                self::PACK_VERSION,
                1,
                1,
                1,
                substr($hash, 1),
                false,
                ResourcePackType::Resources,
            ),
            static fn (): ResourcePackChunkDataPacket => new ResourcePackChunkDataPacket(
                self::PACK_ID,
                self::PACK_VERSION,
                -1,
                0,
                'x',
            ),
            static fn (): ResourcePackChunkDataPacket => new ResourcePackChunkDataPacket(
                self::PACK_ID,
                self::PACK_VERSION,
                0,
                -1,
                'x',
            ),
            static fn (): ResourcePackChunkDataPacket => new ResourcePackChunkDataPacket(
                self::PACK_ID,
                self::PACK_VERSION,
                0,
                0,
                '',
            ),
            static fn (): ResourcePackChunkDataPacket => new ResourcePackChunkDataPacket(
                self::PACK_ID,
                self::PACK_VERSION,
                0,
                0,
                str_repeat('x', CodecSupport::MAX_RESOURCE_PACK_CHUNK_BYTES + 1),
            ),
            static fn (): ResourcePackChunkRequestPacket => new ResourcePackChunkRequestPacket(
                self::PACK_ID,
                self::PACK_VERSION,
                0x80000000,
            ),
        ];

        foreach ($operations as $operation) {
            try {
                $operation();
                self::fail('Invalid resource-pack transfer value was accepted.');
            } catch (InvalidValueException) {
                self::addToAssertionCount(1);
            }
        }
    }

    public function testExactTransferBoundsAreAccepted(): void
    {
        $dataInfo = new ResourcePackDataInfoPacket(
            self::PACK_ID,
            self::PACK_VERSION,
            CodecSupport::MAX_RESOURCE_PACK_CHUNK_BYTES,
            CodecSupport::MAX_RESOURCE_PACK_CHUNKS,
            PHP_INT_MAX,
            str_repeat('h', CodecSupport::RESOURCE_PACK_HASH_BYTES),
            false,
            ResourcePackType::WorldTemplate,
        );
        self::assertEquals($dataInfo, ResourcePackDataInfoPacket::decode($dataInfo->encode()));

        $chunk = new ResourcePackChunkDataPacket(
            self::PACK_ID,
            self::PACK_VERSION,
            0x7fffffff,
            PHP_INT_MAX,
            str_repeat('x', CodecSupport::MAX_RESOURCE_PACK_CHUNK_BYTES),
        );
        self::assertEquals($chunk, ResourcePackChunkDataPacket::decode($chunk->encode()));
    }

    public function testMalformedWireValuesAreRejectedBeforeAllocation(): void
    {
        $identity = "\x2a" . self::PACK_ID . '_' . self::PACK_VERSION;
        $hash = str_repeat('h', CodecSupport::RESOURCE_PACK_HASH_BYTES);
        $validInfo = $identity
            . "\x00\x00\x04\x00\x01\x00\x00\x00"
            . "\x01\x00\x00\x00\x00\x00\x00\x00"
            . "\x20" . $hash . "\x00\x06";
        $validChunk = $identity
            . "\x00\x00\x00\x00"
            . "\x00\x00\x00\x00\x00\x00\x00\x00"
            . "\x01x";

        $malformed = [
            [PacketIds::RESOURCE_PACK_CHUNK_REQUEST, "\x24" . self::PACK_ID . "\x00\x00\x00\x00"],
            [PacketIds::RESOURCE_PACK_CHUNK_REQUEST, "\x2a" . 'x0112233-4455-6677-8899-aabbccddeeff_1.2.3' . "\x00\x00\x00\x00"],
            [PacketIds::RESOURCE_PACK_DATA_INFO, substr_replace($validInfo, "\x00\x00\x00\x00", 43, 4)],
            [PacketIds::RESOURCE_PACK_DATA_INFO, substr_replace($validInfo, "\x01\x00\x10\x00", 47, 4)],
            [PacketIds::RESOURCE_PACK_DATA_INFO, substr_replace($validInfo, str_repeat("\xff", 8), 51, 8)],
            [PacketIds::RESOURCE_PACK_DATA_INFO, substr_replace($validInfo, "\x1f", 59, 1)],
            [PacketIds::RESOURCE_PACK_DATA_INFO, substr_replace($validInfo, "\x02", 92, 1)],
            [PacketIds::RESOURCE_PACK_DATA_INFO, substr_replace($validInfo, "\x09", 93, 1)],
            [PacketIds::RESOURCE_PACK_CHUNK_DATA, substr_replace($validChunk, str_repeat("\xff", 4), 43, 4)],
            [PacketIds::RESOURCE_PACK_CHUNK_DATA, substr_replace($validChunk, str_repeat("\xff", 8), 47, 8)],
            [PacketIds::RESOURCE_PACK_CHUNK_DATA, substr($validChunk, 0, 55) . "\x81\x80\x40"],
            [PacketIds::RESOURCE_PACK_CHUNK_REQUEST, $identity . str_repeat("\xff", 4)],
        ];

        foreach ($malformed as [$packetId, $wire]) {
            try {
                BedrockPacketCodec::decode($packetId, $wire);
                self::fail("Malformed resource-pack transfer packet {$packetId} was accepted.");
            } catch (CodecException) {
                self::addToAssertionCount(1);
            }
        }
    }
}
