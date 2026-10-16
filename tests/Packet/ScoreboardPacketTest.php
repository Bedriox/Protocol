<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Tests\Packet;

use Bedriox\Protocol\Exception\CodecException;
use Bedriox\Protocol\Exception\InvalidValueException;
use Bedriox\Protocol\Packet\BedrockPacketCodec;
use Bedriox\Protocol\Packet\PacketIds;
use Bedriox\Protocol\Packet\RemoveObjectivePacket;
use Bedriox\Protocol\Packet\ScoreboardDisplaySlot;
use Bedriox\Protocol\Packet\ScoreboardIdentityAction;
use Bedriox\Protocol\Packet\ScoreboardIdentityEntry;
use Bedriox\Protocol\Packet\ScoreboardScoreEntry;
use Bedriox\Protocol\Packet\ScoreboardSortOrder;
use Bedriox\Protocol\Packet\SetDisplayObjectivePacket;
use Bedriox\Protocol\Packet\SetScoreboardIdentityPacket;
use Bedriox\Protocol\Packet\SetScorePacket;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ScoreboardPacketTest extends TestCase
{
    private const string DISPLAY_VECTOR = '077369646562617205626f6172640ac2a76242656472696f780564756d6d7902';
    private const string REMOVE_OBJECTIVE_VECTOR = '05626f617264';
    private const string FAKE_SCORE_VECTOR = '0103106368616e676566616b65706c61796572f60105626f6172640b000000075450533a203230';
    private const string REGISTER_IDENTITY_VECTOR = '0001f601019007';
    private const string CLEAR_IDENTITY_VECTOR = '0101f60100';

    public function testExactCurrentDisplayAndRemovalVectorsAreRegistered(): void
    {
        $display = new SetDisplayObjectivePacket(
            ScoreboardDisplaySlot::SIDEBAR,
            'board',
            "\u{00a7}bBedriox",
            'dummy',
            ScoreboardSortOrder::DESCENDING,
        );
        $remove = new RemoveObjectivePacket('board');

        self::assertSame(self::DISPLAY_VECTOR, bin2hex($display->encode()));
        self::assertSame(self::REMOVE_OBJECTIVE_VECTOR, bin2hex($remove->encode()));
        self::assertSame(106, PacketIds::REMOVE_OBJECTIVE);
        self::assertSame(107, PacketIds::SET_DISPLAY_OBJECTIVE);
        self::assertSame(PacketIds::SET_DISPLAY_OBJECTIVE, BedrockPacketCodec::packetId($display));
        self::assertSame(PacketIds::REMOVE_OBJECTIVE, BedrockPacketCodec::packetId($remove));
        self::assertTrue(BedrockPacketCodec::supportsDecoding(PacketIds::SET_DISPLAY_OBJECTIVE));
        self::assertTrue(BedrockPacketCodec::supportsDecoding(PacketIds::REMOVE_OBJECTIVE));
        self::assertEquals($display, BedrockPacketCodec::decode(PacketIds::SET_DISPLAY_OBJECTIVE, $display->encode()));
        self::assertEquals($remove, BedrockPacketCodec::decode(PacketIds::REMOVE_OBJECTIVE, $remove->encode()));
    }

    public function testExactCurrentFakeScoreVectorIsRegistered(): void
    {
        $packet = new SetScorePacket([
            ScoreboardScoreEntry::fakePlayer(123, 'board', 11, 'TPS: 20'),
        ]);

        self::assertSame(self::FAKE_SCORE_VECTOR, bin2hex($packet->encode()));
        self::assertSame(108, PacketIds::SET_SCORE);
        self::assertSame(PacketIds::SET_SCORE, $packet->packetId());
        self::assertSame(PacketIds::SET_SCORE, BedrockPacketCodec::packetId($packet));
        self::assertTrue(BedrockPacketCodec::supportsDecoding(PacketIds::SET_SCORE));
        self::assertEquals($packet, BedrockPacketCodec::decode(PacketIds::SET_SCORE, $packet->encode()));
    }

    public function testEveryCurrentScoreEntryTypeRoundTrips(): void
    {
        $packet = new SetScorePacket([
            ScoreboardScoreEntry::remove(PHP_INT_MIN),
            ScoreboardScoreEntry::remove(-1, 'board'),
            ScoreboardScoreEntry::player(PHP_INT_MAX, 'board', PHP_INT_MIN >> 32, -456),
            ScoreboardScoreEntry::entity(1, 'board', 0x7fffffff, PHP_INT_MAX),
            ScoreboardScoreEntry::fakePlayer(2, 'board', -0x80000000, "\u{00a7}aName"),
        ]);

        self::assertEquals($packet, SetScorePacket::decode($packet->encode()));
    }

    public function testEveryDisplaySlotAndSortOrderRoundTrips(): void
    {
        foreach (ScoreboardDisplaySlot::cases() as $slot) {
            foreach (ScoreboardSortOrder::cases() as $sortOrder) {
                $packet = new SetDisplayObjectivePacket($slot, 'board', 'Board', sortOrder: $sortOrder);
                self::assertEquals($packet, SetDisplayObjectivePacket::decode($packet->encode()));
            }
        }
    }

    public function testExactCurrentIdentityVectorsAreRegistered(): void
    {
        $register = new SetScoreboardIdentityPacket(
            ScoreboardIdentityAction::REGISTER,
            [new ScoreboardIdentityEntry(123, 456)],
        );
        $clear = new SetScoreboardIdentityPacket(
            ScoreboardIdentityAction::CLEAR,
            [new ScoreboardIdentityEntry(123)],
        );

        self::assertSame(self::REGISTER_IDENTITY_VECTOR, bin2hex($register->encode()));
        self::assertSame(self::CLEAR_IDENTITY_VECTOR, bin2hex($clear->encode()));
        self::assertSame(112, PacketIds::SET_SCOREBOARD_IDENTITY);
        self::assertSame(PacketIds::SET_SCOREBOARD_IDENTITY, BedrockPacketCodec::packetId($register));
        self::assertTrue(BedrockPacketCodec::supportsDecoding(PacketIds::SET_SCOREBOARD_IDENTITY));
        self::assertEquals($register, BedrockPacketCodec::decode(PacketIds::SET_SCOREBOARD_IDENTITY, $register->encode()));
        self::assertEquals($clear, SetScoreboardIdentityPacket::decode($clear->encode()));
    }

    /** @param class-string $packetClass */
    #[DataProvider('exactPacketVectors')]
    public function testEveryExactVectorTruncationAndTrailingByteFailsClosed(string $packetClass, string $hex): void
    {
        $wire = hex2bin($hex);
        self::assertIsString($wire);
        for ($length = 0; $length < strlen($wire); ++$length) {
            try {
                $packetClass::decode(substr($wire, 0, $length));
                self::fail("Truncated scoreboard payload was accepted at {$length} bytes.");
            } catch (CodecException) {
                self::addToAssertionCount(1);
            }
        }

        $this->expectException(CodecException::class);
        $packetClass::decode($wire . "\0");
    }

    /** @return iterable<string, array{class-string, string}> */
    public static function exactPacketVectors(): iterable
    {
        yield 'display' => [SetDisplayObjectivePacket::class, self::DISPLAY_VECTOR];
        yield 'remove objective' => [RemoveObjectivePacket::class, self::REMOVE_OBJECTIVE_VECTOR];
        yield 'score' => [SetScorePacket::class, self::FAKE_SCORE_VECTOR];
        yield 'register identity' => [SetScoreboardIdentityPacket::class, self::REGISTER_IDENTITY_VECTOR];
        yield 'clear identity' => [SetScoreboardIdentityPacket::class, self::CLEAR_IDENTITY_VECTOR];
    }

    #[DataProvider('malformedScorePayloads')]
    public function testMalformedScorePayloadsFailClosed(string $wire): void
    {
        $this->expectException(CodecException::class);
        SetScorePacket::decode($wire);
    }

    /** @return iterable<string, array{string}> */
    public static function malformedScorePayloads(): iterable
    {
        $fake = hex2bin(self::FAKE_SCORE_VECTOR);
        self::assertIsString($fake);

        yield 'entry count exceeds limit' => ["\x81\x08"];
        yield 'unknown entry ordinal' => ["\x01\x04"];
        yield 'type name does not match ordinal' => [substr_replace($fake, "\x10changefakeentity", 2, 17)];
        yield 'noncanonical entry ordinal' => [substr_replace($fake, "\x83\x00", 1, 1)];
        yield 'remove optional flag is not boolean' => ["\x01\x00\x06remove\x02\x02"];
        yield 'empty changed objective' => ["\x01\x03\x10changefakeplayer\x02\x00\x00\x00\x00\x00\x01x"];
    }

    #[DataProvider('malformedIdentityPayloads')]
    public function testMalformedIdentityPayloadsFailClosed(string $wire): void
    {
        $this->expectException(CodecException::class);
        SetScoreboardIdentityPacket::decode($wire);
    }

    /** @return iterable<string, array{string}> */
    public static function malformedIdentityPayloads(): iterable
    {
        yield 'unknown action' => ["\x02\x00"];
        yield 'entry count exceeds limit' => ["\x00\x81\x08"];
        yield 'register omits player ID' => ["\x00\x01\x02\x00"];
        yield 'clear includes player ID' => ["\x01\x01\x02\x01\x02"];
        yield 'optional flag is not boolean' => ["\x00\x01\x02\x02"];
    }

    public function testUnknownDisplayValuesFailClosed(): void
    {
        $this->expectException(CodecException::class);
        SetDisplayObjectivePacket::decode("\x07unknown\x05board\x00\x05dummy\x00");
    }

    public function testLocalEntryBoundsAndIdentityShapeAreValidated(): void
    {
        $entry = ScoreboardScoreEntry::remove(1);
        try {
            new SetScorePacket(array_fill(0, 1_025, $entry));
            self::fail('Oversized scoreboard score list was accepted.');
        } catch (InvalidValueException) {
            self::addToAssertionCount(1);
        }

        $this->expectException(InvalidValueException::class);
        new SetScoreboardIdentityPacket(
            ScoreboardIdentityAction::REGISTER,
            [new ScoreboardIdentityEntry(1)],
        );
    }

    public function testScoreMustFitSignedWireInteger(): void
    {
        $this->expectException(InvalidValueException::class);
        ScoreboardScoreEntry::fakePlayer(1, 'board', 0x80000000, 'Name');
    }
}
