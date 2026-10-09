<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Tests\Packet;

use Bedriox\Protocol\Exception\BufferUnderflowException;
use Bedriox\Protocol\Exception\InvalidValueException;
use Bedriox\Protocol\Exception\MalformedDataException;
use Bedriox\Protocol\Packet\BedrockPacketCodec;
use Bedriox\Protocol\Packet\BlockPickRequestPacket;
use Bedriox\Protocol\Packet\BlockPosition;
use Bedriox\Protocol\Packet\PacketIds;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class BlockPickRequestPacketTest extends TestCase
{
    public function testKnownVectorAndRegistryRoundTrip(): void
    {
        $packet = new BlockPickRequestPacket(new BlockPosition(-2, 64, 300), true, 8);
        $expected = "\x03\x80\x01\xd8\x04\x01\x08";

        self::assertSame(PacketIds::BLOCK_PICK_REQUEST, $packet->packetId());
        self::assertSame($expected, BedrockPacketCodec::encode($packet));

        $decoded = BedrockPacketCodec::decode(PacketIds::BLOCK_PICK_REQUEST, $expected);
        self::assertEquals($packet, $decoded);
    }

    #[DataProvider('truncations')]
    public function testRejectsEveryTruncation(string $payload): void
    {
        $this->expectException(BufferUnderflowException::class);
        BlockPickRequestPacket::decode($payload);
    }

    /** @return iterable<string, array{string}> */
    public static function truncations(): iterable
    {
        $payload = "\x03\x80\x01\xd8\x04\x01\x08";
        for ($length = 0; $length < strlen($payload); ++$length) {
            yield 'length ' . $length => [substr($payload, 0, $length)];
        }
    }

    public function testRejectsTrailingData(): void
    {
        $this->expectException(MalformedDataException::class);
        BlockPickRequestPacket::decode("\x00\x00\x00\x00\x00\xff");
    }

    public function testRejectsInvalidBoolean(): void
    {
        $this->expectException(MalformedDataException::class);
        BlockPickRequestPacket::decode("\x00\x00\x00\x02\x00");
    }

    public function testRejectsOutOfRangeHotbarByte(): void
    {
        $this->expectException(InvalidValueException::class);
        new BlockPickRequestPacket(new BlockPosition(0, 0, 0), false, 256);
    }
}
