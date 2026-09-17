<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Tests\Packet;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Bedriox\Protocol\Exception\CodecException;
use Bedriox\Protocol\Packet\BedrockPacketCodec;
use Bedriox\Protocol\Packet\PacketIds;
use Bedriox\Protocol\Packet\RemoveActorPacket;

final class RemoveActorPacketTest extends TestCase
{
    /** @return iterable<string, array{int, string}> */
    public static function literalVectors(): iterable
    {
        yield 'zero' => [0, '00'];
        yield 'one' => [1, '02'];
        yield 'negative one' => [-1, '01'];
        yield 'signed maximum' => [PHP_INT_MAX, 'feffffffffffffffff01'];
        yield 'signed minimum' => [PHP_INT_MIN, 'ffffffffffffffffff01'];
    }

    #[DataProvider('literalVectors')]
    public function testSignedUniqueIdUsesExactProtocol2193WireForm(int $actorUniqueId, string $hex): void
    {
        $packet = new RemoveActorPacket($actorUniqueId);

        self::assertSame(PacketIds::REMOVE_ACTOR, $packet->packetId());
        self::assertSame(PacketIds::REMOVE_ACTOR, BedrockPacketCodec::packetId($packet));
        self::assertSame($hex, bin2hex(BedrockPacketCodec::encode($packet)));
        $wire = hex2bin($hex);
        self::assertIsString($wire);
        self::assertEquals($packet, BedrockPacketCodec::decode(PacketIds::REMOVE_ACTOR, $wire));
    }

    /** @return iterable<string, array{string}> */
    public static function malformedPayloads(): iterable
    {
        yield 'empty' => [''];
        yield 'unterminated' => ["\x80"];
        yield 'non-canonical zero' => ["\x80\x00"];
        yield 'overflow' => [str_repeat("\xff", 10) . "\x02"];
        yield 'trailing data' => ["\x00\x00"];
    }

    #[DataProvider('malformedPayloads')]
    public function testMalformedPayloadFailsClosed(string $payload): void
    {
        $this->expectException(CodecException::class);
        RemoveActorPacket::decode($payload);
    }
}
