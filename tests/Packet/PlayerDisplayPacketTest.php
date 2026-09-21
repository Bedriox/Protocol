<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Tests\Packet;

use Bedriox\Protocol\Exception\CodecException;
use Bedriox\Protocol\Exception\InvalidValueException;
use Bedriox\Protocol\Packet\BedrockPacketCodec;
use Bedriox\Protocol\Packet\Packet;
use Bedriox\Protocol\Packet\PacketIds;
use Bedriox\Protocol\Packet\SetTitlePacket;
use Bedriox\Protocol\Packet\SetTitleType;
use Bedriox\Protocol\Packet\ToastRequestPacket;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PlayerDisplayPacketTest extends TestCase
{
    /** @return iterable<string, array{Packet, int, string}> */
    public static function vectors(): iterable
    {
        yield 'title with animation fields' => [
            new SetTitlePacket(SetTitleType::Title, 'Welcome', 10, 70, 20),
            PacketIds::SET_TITLE,
            '040757656c636f6d65148c0128000000',
        ];
        yield 'subtitle' => [
            SetTitlePacket::subtitle('Subtitle'),
            PacketIds::SET_TITLE,
            '06085375627469746c65000000000000',
        ];
        yield 'action bar' => [
            SetTitlePacket::actionBar('Action'),
            PacketIds::SET_TITLE,
            '0806416374696f6e000000000000',
        ];
        yield 'title times' => [
            SetTitlePacket::times(10, 70, 20),
            PacketIds::SET_TITLE,
            '0a00148c0128000000',
        ];
        yield 'clear title' => [
            SetTitlePacket::clear(),
            PacketIds::SET_TITLE,
            '0000000000000000',
        ];
        yield 'reset title' => [
            SetTitlePacket::reset(),
            PacketIds::SET_TITLE,
            '0200000000000000',
        ];
        yield 'title json' => [
            SetTitlePacket::titleJson('{"text":"Title"}'),
            PacketIds::SET_TITLE,
            '0c107b2274657874223a225469746c65227d000000000000',
        ];
        yield 'subtitle json' => [
            SetTitlePacket::subtitleJson('{"text":"Sub"}'),
            PacketIds::SET_TITLE,
            '0e0e7b2274657874223a22537562227d000000000000',
        ];
        yield 'action bar json' => [
            SetTitlePacket::actionBarJson('{"text":"Bar"}'),
            PacketIds::SET_TITLE,
            '100e7b2274657874223a22426172227d000000000000',
        ];
        yield 'toast' => [
            new ToastRequestPacket('A', 'B'),
            PacketIds::TOAST_REQUEST,
            '01410142',
        ];
    }

    #[DataProvider('vectors')]
    public function testMatchesCurrentProtocolVectors(Packet $packet, int $packetId, string $hex): void
    {
        $wire = hex2bin($hex);
        self::assertIsString($wire);
        self::assertSame($wire, BedrockPacketCodec::encode($packet));
        self::assertEquals($packet, BedrockPacketCodec::decode($packetId, $wire));
        self::assertSame($packetId, BedrockPacketCodec::packetId($packet));

        for ($length = 0; $length < strlen($wire); ++$length) {
            try {
                BedrockPacketCodec::decode($packetId, substr($wire, 0, $length));
                self::fail("Display packet truncation at {$length} was accepted.");
            } catch (CodecException) {
                self::addToAssertionCount(1);
            }
        }
    }

    public function testCurrentPacketIdsAndTitleDiscriminators(): void
    {
        self::assertSame(88, PacketIds::SET_TITLE);
        self::assertSame(186, PacketIds::TOAST_REQUEST);
        foreach (SetTitleType::cases() as $index => $type) {
            self::assertSame($index, $type->value);
        }
    }

    public function testRejectsMalformedDisplayPackets(): void
    {
        foreach ([
            [PacketIds::SET_TITLE, "\x12\0\0\0\0\0\0"],
            [PacketIds::SET_TITLE, hex2bin('000000000000000000')],
            [PacketIds::TOAST_REQUEST, "\1A"],
            [PacketIds::TOAST_REQUEST, "\0\0\0"],
        ] as [$packetId, $wire]) {
            self::assertIsString($wire);
            try {
                BedrockPacketCodec::decode($packetId, $wire);
                self::fail('Malformed display packet was accepted.');
            } catch (CodecException) {
                self::addToAssertionCount(1);
            }
        }
    }

    public function testConstructorBoundsAreEnforced(): void
    {
        foreach ([
            static fn (): SetTitlePacket => SetTitlePacket::title(str_repeat('x', 4_097)),
            static fn (): ToastRequestPacket => new ToastRequestPacket(str_repeat('x', 4_097), 'body'),
            static fn (): ToastRequestPacket => new ToastRequestPacket('title', "\xff"),
        ] as $operation) {
            try {
                $operation();
                self::fail('Invalid display packet constructor input was accepted.');
            } catch (InvalidValueException) {
                self::addToAssertionCount(1);
            }
        }
    }
}
