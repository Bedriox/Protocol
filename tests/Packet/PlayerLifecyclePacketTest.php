<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Tests\Packet;

use Bedriox\Protocol\Exception\CodecException;
use Bedriox\Protocol\Exception\InvalidValueException;
use Bedriox\Protocol\Packet\ActorEventPacket;
use Bedriox\Protocol\Packet\ActorEventType;
use Bedriox\Protocol\Packet\BedrockPacketCodec;
use Bedriox\Protocol\Packet\DeathInfoPacket;
use Bedriox\Protocol\Packet\PacketIds;
use Bedriox\Protocol\Packet\RespawnPacket;
use Bedriox\Protocol\Packet\RespawnState;
use Bedriox\Protocol\Value\UnsignedLong;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PlayerLifecyclePacketTest extends TestCase
{
    public function testActorLifecycleVectorsAndRegistry(): void
    {
        foreach ([
            ['07020000', ActorEventType::Hurt],
            ['07030000', ActorEventType::Death],
            ['07090000', ActorEventType::UseItem],
            ['07120000', ActorEventType::Respawn],
            ['07390000', ActorEventType::EatingItem],
        ] as [$hex, $type]) {
            $packet = new ActorEventPacket(UnsignedLong::fromInt(7), $type);
            self::assertSame($hex, bin2hex(BedrockPacketCodec::encode($packet)));
            self::assertSame(PacketIds::ACTOR_EVENT, BedrockPacketCodec::packetId($packet));
            self::assertEquals($packet, BedrockPacketCodec::decode(PacketIds::ACTOR_EVENT, hex2bin($hex) ?: ''));
        }
    }

    public function testActorEventCarriesCurrentOptionalFirePosition(): void
    {
        $packet = new ActorEventPacket(UnsignedLong::fromInt(7), ActorEventType::Hurt, -2, 1.5, 2.5, -3.5);
        self::assertSame('070203010000c03f00002040000060c0', bin2hex($packet->encode()));
        self::assertEquals($packet, ActorEventPacket::decode($packet->encode()));
    }

    public function testRespawnVectorAndStates(): void
    {
        foreach (RespawnState::cases() as $state) {
            $packet = new RespawnPacket(1.5, 64.0, -2.25, $state, UnsignedLong::fromInt(7));
            $expected = '0000c03f00008042000010c0' . sprintf('%02x', $state->value) . '07';
            self::assertSame($expected, bin2hex($packet->encode()));
            self::assertSame(PacketIds::RESPAWN, BedrockPacketCodec::packetId($packet));
            self::assertEquals($packet, BedrockPacketCodec::decode(PacketIds::RESPAWN, $packet->encode()));
        }
    }

    public function testDeathInformationVector(): void
    {
        $packet = new DeathInfoPacket('death.fell', ['Player']);
        self::assertSame('0a64656174682e66656c6c0106506c61796572', bin2hex($packet->encode()));
        self::assertSame(PacketIds::DEATH_INFO, BedrockPacketCodec::packetId($packet));
        self::assertEquals($packet, BedrockPacketCodec::decode(PacketIds::DEATH_INFO, $packet->encode()));
    }

    #[DataProvider('malformedPayloads')]
    public function testMalformedLifecyclePayloadsFailClosed(int $packetId, string $payload): void
    {
        $this->expectException(CodecException::class);
        BedrockPacketCodec::decode($packetId, $payload);
    }

    /** @return iterable<string, array{int, string}> */
    public static function malformedPayloads(): iterable
    {
        yield 'unknown actor event' => [PacketIds::ACTOR_EVENT, "\x07\xff\x00\x00"];
        yield 'invalid actor optional flag' => [PacketIds::ACTOR_EVENT, "\x07\x02\x00\x02"];
        yield 'truncated fire position' => [PacketIds::ACTOR_EVENT, "\x07\x02\x00\x01\0"];
        yield 'unknown respawn state' => [PacketIds::RESPAWN, str_repeat("\0", 12) . "\x03\x07"];
        yield 'trailing respawn byte' => [PacketIds::RESPAWN, str_repeat("\0", 12) . "\x00\x07\0"];
        yield 'too many death parameters' => [PacketIds::DEATH_INFO, "\0\x11"];
        yield 'truncated death parameter' => [PacketIds::DEATH_INFO, "\0\x01\x01"];
    }

    public function testPartialActorFireCoordinatesAreRejected(): void
    {
        $this->expectException(InvalidValueException::class);
        new ActorEventPacket(UnsignedLong::fromInt(1), ActorEventType::Hurt, fireX: 1.0);
    }
}
