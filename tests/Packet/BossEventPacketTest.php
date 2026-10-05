<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Tests\Packet;

use Bedriox\Protocol\Exception\CodecException;
use Bedriox\Protocol\Exception\InvalidValueException;
use Bedriox\Protocol\Packet\BedrockPacketCodec;
use Bedriox\Protocol\Packet\BossEventAction;
use Bedriox\Protocol\Packet\BossEventColor;
use Bedriox\Protocol\Packet\BossEventOverlay;
use Bedriox\Protocol\Packet\BossEventPacket;
use Bedriox\Protocol\Packet\PacketIds;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class BossEventPacketTest extends TestCase
{
    private const string CREATE_VECTOR = 'f6010006447261676f6e000000403f0500';

    public function testCurrentCreateVectorIsExactAndRegistered(): void
    {
        $packet = new BossEventPacket(
            123,
            BossEventAction::CREATE,
            'Dragon',
            '',
            0.75,
            BossEventColor::PURPLE,
            BossEventOverlay::PROGRESS,
        );

        self::assertSame(self::CREATE_VECTOR, bin2hex($packet->encode()));
        self::assertSame(PacketIds::BOSS_EVENT, $packet->packetId());
        self::assertSame(PacketIds::BOSS_EVENT, BedrockPacketCodec::packetId($packet));
        self::assertEquals($packet, BedrockPacketCodec::decode(PacketIds::BOSS_EVENT, $packet->encode()));
    }

    #[DataProvider('currentSchemaValues')]
    public function testEveryCurrentSchemaValueRoundTrips(
        BossEventAction $action,
        BossEventColor $color,
        BossEventOverlay $overlay,
    ): void {
        $packet = new BossEventPacket(
            PHP_INT_MIN,
            $action,
            'Boss',
            'Filtered',
            1.0,
            $color,
            $overlay,
        );

        self::assertEquals($packet, BossEventPacket::decode($packet->encode()));
    }

    /** @return iterable<string, array{BossEventAction, BossEventColor, BossEventOverlay}> */
    public static function currentSchemaValues(): iterable
    {
        $actions = BossEventAction::cases();
        $colors = BossEventColor::cases();
        $overlays = BossEventOverlay::cases();
        $count = max(count($actions), count($colors), count($overlays));
        for ($index = 0; $index < $count; ++$index) {
            yield 'schema value ' . $index => [
                $actions[$index % count($actions)],
                $colors[$index % count($colors)],
                $overlays[$index % count($overlays)],
            ];
        }
    }

    public function testMaximumTitlesAndSignedEntityIdRoundTrip(): void
    {
        $packet = new BossEventPacket(
            PHP_INT_MAX,
            BossEventAction::UPDATE_NAME,
            str_repeat('a', 4_096),
            str_repeat('b', 4_096),
            0.0,
            BossEventColor::REBECCA_PURPLE,
            BossEventOverlay::NOTCHED_20,
        );

        self::assertEquals($packet, BossEventPacket::decode($packet->encode()));
    }

    public function testEveryCreateVectorTruncationAndTrailingByteFailClosed(): void
    {
        $wire = hex2bin(self::CREATE_VECTOR);
        self::assertIsString($wire);
        for ($length = 0; $length < strlen($wire); ++$length) {
            try {
                BossEventPacket::decode(substr($wire, 0, $length));
                self::fail("Truncated boss-event packet was accepted at {$length} bytes.");
            } catch (CodecException) {
                self::addToAssertionCount(1);
            }
        }

        $this->expectException(CodecException::class);
        BossEventPacket::decode($wire . "\0");
    }

    #[DataProvider('malformedPayloads')]
    public function testMalformedCurrentSchemaValuesFailClosed(string $wire): void
    {
        $this->expectException(CodecException::class);
        BossEventPacket::decode($wire);
    }

    /** @return iterable<string, array{string}> */
    public static function malformedPayloads(): iterable
    {
        $wire = hex2bin(self::CREATE_VECTOR);
        self::assertIsString($wire);

        yield 'noncanonical entity ID' => ["\xf6\x81\x00" . substr($wire, 2)];
        yield 'unknown action' => [substr_replace($wire, "\x09", 2, 1)];
        yield 'invalid title UTF-8' => [substr_replace($wire, "\x01\xff", 3, 7)];
        yield 'non-finite progress' => [substr_replace($wire, pack('g', INF), 11, 4)];
        yield 'negative progress' => [substr_replace($wire, pack('g', -0.1), 11, 4)];
        yield 'progress above one' => [substr_replace($wire, pack('g', 1.01), 11, 4)];
        yield 'unknown color' => [substr_replace($wire, "\x08", 15, 1)];
        yield 'unknown overlay' => [substr_replace($wire, "\x05", 16, 1)];
        yield 'title above byte limit' => ["\xf6\x01\x00\x81\x20"];
    }

    #[DataProvider('invalidConstruction')]
    public function testInvalidLocalValuesCannotBeConstructed(string $title, float $healthPercentage): void
    {
        $this->expectException(InvalidValueException::class);
        new BossEventPacket(1, BossEventAction::CREATE, $title, '', $healthPercentage);
    }

    /** @return iterable<string, array{string, float}> */
    public static function invalidConstruction(): iterable
    {
        yield 'title too long' => [str_repeat('a', 4_097), 0.5];
        yield 'title invalid UTF-8' => ["\xff", 0.5];
        yield 'negative progress' => ['', -0.1];
        yield 'progress above one' => ['', 1.1];
        yield 'infinite progress' => ['', INF];
        yield 'NaN progress' => ['', NAN];
    }
}
