<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Tests\Packet;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Bedriox\Protocol\Exception\CodecException;
use Bedriox\Protocol\Packet\BedrockPacketCodec;
use Bedriox\Protocol\Packet\PacketIds;
use Bedriox\Protocol\Packet\SetPlayerInventoryOptionsPacket;

final class SetPlayerInventoryOptionsPacketTest extends TestCase
{
    public function testCurrentInventoryOptionsRoundTrip(): void
    {
        $packet = new SetPlayerInventoryOptionsPacket(6, 1, true, 1, 2);
        self::assertSame('0c02010204', bin2hex($packet->encode()));
        self::assertEquals($packet, BedrockPacketCodec::decode(PacketIds::SET_PLAYER_INVENTORY_OPTIONS, $packet->encode()));
        self::assertSame(PacketIds::SET_PLAYER_INVENTORY_OPTIONS, BedrockPacketCodec::packetId($packet));
    }

    /** @return iterable<string, array{string}> */
    public static function malformed(): iterable
    {
        yield 'truncated' => ["\0\0\0\0"];
        yield 'invalid boolean' => ["\0\0\2\0\0"];
        yield 'left enum' => ["\x0e\0\0\0\0"];
        yield 'trailing' => ["\0\0\0\0\0\0"];
    }

    #[DataProvider('malformed')]
    public function testMalformedOptionsFailClosed(string $payload): void
    {
        $this->expectException(CodecException::class);
        SetPlayerInventoryOptionsPacket::decode($payload);
    }
}
