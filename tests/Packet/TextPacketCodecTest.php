<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Tests\Packet;

use Bedriox\Protocol\Exception\CodecException;
use Bedriox\Protocol\Exception\InvalidValueException;
use Bedriox\Protocol\Packet\BedrockPacketCodec;
use Bedriox\Protocol\Packet\Packet;
use Bedriox\Protocol\Packet\PacketIds;
use Bedriox\Protocol\Packet\SystemTextPacket;
use Bedriox\Protocol\Packet\TextPacketType;
use Bedriox\Protocol\Packet\TextPacket;
use Bedriox\Protocol\Packet\TextPayloadVariant;
use Bedriox\Protocol\Packet\TranslatedTextPacket;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class TextPacketCodecTest extends TestCase
{
    /** @return iterable<string, array{Packet, string}> */
    public static function vectors(): iterable
    {
        yield 'untranslated system message' => [
            new SystemTextPacket('Server notice'),
            '0000060d536572766572206e6f74696365000000',
        ];
        yield 'translated player death message' => [
            new TranslatedTextPacket('death.attack.player', ['Alex', 'Sam']),
            '0102021364656174682e61747461636b2e706c617965720204416c65780353616d000000',
        ];
        yield 'translated message with optional metadata' => [
            new TranslatedTextPacket('key', ['value'], 'x', 'p', 'filtered'),
            '010202036b6579010576616c756501780170010866696c7465726564',
        ];
        yield 'raw message' => [
            TextPacket::raw('hello'),
            '0000000568656c6c6f000000',
        ];
        yield 'popup with parameters' => [
            TextPacket::popup('message', ['p']),
            '000203076d657373616765010170000000',
        ];
        yield 'jukebox popup' => [
            TextPacket::jukeboxPopup('record', ['name']),
            '000204067265636f726401046e616d65000000',
        ];
        yield 'tip' => [
            TextPacket::tip('hint'),
            '0000050468696e74000000',
        ];
        yield 'whisper' => [
            TextPacket::whisper('Alex', 'secret'),
            '00010704416c657806736563726574000000',
        ];
        yield 'announcement' => [
            TextPacket::announcement('Server', 'notice'),
            '00010806536572766572066e6f74696365000000',
        ];
        yield 'whisper json' => [
            TextPacket::whisperJson('{"text":"secret"}'),
            '000009117b2274657874223a22736563726574227d000000',
        ];
        yield 'json' => [
            TextPacket::json('{"text":"hello"}'),
            '00000a107b2274657874223a2268656c6c6f227d000000',
        ];
        yield 'announcement json' => [
            TextPacket::announcementJson('{"text":"notice"}'),
            '00000b117b2274657874223a226e6f74696365227d000000',
        ];
    }

    #[DataProvider('vectors')]
    public function testMatchesCurrentProtocolVectors(Packet $packet, string $hex): void
    {
        $wire = hex2bin($hex);
        self::assertIsString($wire);
        self::assertSame($wire, BedrockPacketCodec::encode($packet));
        self::assertEquals($packet, BedrockPacketCodec::decode(PacketIds::TEXT, $wire));
        self::assertSame(PacketIds::TEXT, BedrockPacketCodec::packetId($packet));

        for ($length = 0; $length < strlen($wire); ++$length) {
            try {
                BedrockPacketCodec::decode(PacketIds::TEXT, substr($wire, 0, $length));
                self::fail("Text packet truncation at {$length} was accepted.");
            } catch (CodecException) {
                self::addToAssertionCount(1);
            }
        }

        try {
            BedrockPacketCodec::decode(PacketIds::TEXT, $wire . "\0");
            self::fail('Trailing text packet data was accepted.');
        } catch (CodecException) {
            self::addToAssertionCount(1);
        }
    }

    public function testTypedDiscriminatorsMatchProtocol2193(): void
    {
        self::assertSame(0, TextPayloadVariant::MessageOnly->value);
        self::assertSame(1, TextPayloadVariant::AuthorAndMessage->value);
        self::assertSame(2, TextPayloadVariant::MessageAndParameters->value);
        self::assertSame(1, TextPacketType::Chat->value);
        self::assertSame(2, TextPacketType::Translation->value);
        self::assertSame(6, TextPacketType::System->value);
        self::assertSame(11, TextPacketType::AnnouncementJson->value);
    }

    /** @return iterable<string, array{string}> */
    public static function malformed(): iterable
    {
        yield 'invalid translation boolean' => ["\2\0\6\1x\0\0\0"];
        yield 'system requests translation' => ["\1\0\6\1x\0\0\0"];
        yield 'translation omits translation marker' => ["\0\2\2\1x\0\0\0\0"];
        yield 'system uses parameter variant' => ["\0\2\6\1x\0\0\0"];
        yield 'translation uses system type' => ["\1\2\6\1x\0\0\0\0"];
        yield 'unknown payload variant' => ["\0\3\6"];
        yield 'unknown text type' => ["\0\0\xff"];
        yield 'empty system message' => ["\0\0\6\0\0\0\0"];
        yield 'empty translation key' => ["\1\2\2\0\0\0\0\0"];
        yield 'translation parameter overflow' => ["\1\2\2\1x\x11"];
        yield 'noncanonical translation parameter count' => ["\1\2\2\1x\x80\0"];
        yield 'invalid optional filter boolean' => ["\0\0\6\1x\0\0\2"];
    }

    #[DataProvider('malformed')]
    public function testRejectsMalformedTextPayloads(string $wire): void
    {
        $this->expectException(CodecException::class);
        BedrockPacketCodec::decode(PacketIds::TEXT, $wire);
    }

    public function testConstructorBoundsAreEnforced(): void
    {
        foreach ([
            static fn (): SystemTextPacket => new SystemTextPacket(''),
            static fn (): SystemTextPacket => new SystemTextPacket(str_repeat('x', 4_097)),
            static fn (): TranslatedTextPacket => new TranslatedTextPacket(''),
            static fn (): TranslatedTextPacket => new TranslatedTextPacket('key', array_fill(0, 17, 'value')),
            static fn (): object => (new \ReflectionClass(TranslatedTextPacket::class))->newInstanceArgs(['key', [123]]),
            static fn (): TranslatedTextPacket => new TranslatedTextPacket('key', [str_repeat('x', 4_097)]),
            static fn (): TextPacket => TextPacket::raw(''),
            static fn (): TextPacket => TextPacket::tip(str_repeat('x', 4_097)),
            static fn (): TextPacket => new TextPacket(TextPacketType::Raw, 'message', 'source'),
            static fn (): TextPacket => new TextPacket(TextPacketType::Tip, 'message', parameters: ['parameter']),
            static fn (): TextPacket => new TextPacket(TextPacketType::Popup, 'message', parameters: array_fill(0, 17, 'value')),
            static fn (): TextPacket => new TextPacket(TextPacketType::System, 'message'),
        ] as $operation) {
            try {
                $operation();
                self::fail('Invalid text packet constructor input was accepted.');
            } catch (InvalidValueException) {
                self::addToAssertionCount(1);
            }
        }
    }
}
