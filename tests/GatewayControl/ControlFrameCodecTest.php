<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Tests\GatewayControl;

use Bedriox\Protocol\Exception\BufferUnderflowException;
use Bedriox\Protocol\Exception\MalformedDataException;
use Bedriox\Protocol\GatewayControl\ControlFrame;
use Bedriox\Protocol\GatewayControl\ControlFrameCodec;
use Bedriox\Protocol\GatewayControl\ControlMessageType;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ControlFrameCodecTest extends TestCase
{
    public function testKnownVectorAndRoundTrip(): void
    {
        $frame = new ControlFrame(
            ControlMessageType::HealthRequest,
            '00112233445566778899aabbccddeeff',
            0x12345678,
            'ok',
        );
        $encoded = ControlFrameCodec::encode($frame);

        self::assertSame(
            '42584743010a000000112233445566778899aabbccddeeff78563412020000006f6b',
            bin2hex($encoded),
        );
        self::assertEquals($frame, ControlFrameCodec::decode($encoded));
    }

    #[DataProvider('truncations')]
    public function testEveryTruncationFails(int $length): void
    {
        $encoded = ControlFrameCodec::encode(new ControlFrame(
            ControlMessageType::PluginMessage,
            '00112233445566778899aabbccddeeff',
            7,
            'payload',
        ));

        $this->expectException(BufferUnderflowException::class);
        ControlFrameCodec::decode(substr($encoded, 0, $length));
    }

    /** @return iterable<string, array{int}> */
    public static function truncations(): iterable
    {
        for ($length = 0; $length < ControlFrameCodec::HEADER_BYTES + 7; ++$length) {
            yield "length-{$length}" => [$length];
        }
    }

    public function testUnknownTypeFlagsAndTrailingBytesFail(): void
    {
        $encoded = ControlFrameCodec::encode(new ControlFrame(
            ControlMessageType::Ready,
            str_repeat('0', 32),
            0,
            '',
        ));

        foreach ([
            substr_replace($encoded, "\xff", 5, 1),
            substr_replace($encoded, "\x02\x00", 6, 2),
            $encoded . "\x00",
        ] as $invalid) {
            try {
                ControlFrameCodec::decode($invalid);
                self::fail('Malformed Gateway control frame was accepted.');
            } catch (MalformedDataException) {
                self::addToAssertionCount(1);
            }
        }
    }
}
