<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Tests\Packet;

use Bedriox\Protocol\Exception\MalformedDataException;
use Bedriox\Protocol\Exception\BufferUnderflowException;
use Bedriox\Protocol\Exception\InvalidValueException;
use Bedriox\Protocol\Packet\BedrockPacketCodec;
use Bedriox\Protocol\Packet\ClientboundCloseFormPacket;
use Bedriox\Protocol\Packet\ModalFormCancelReason;
use Bedriox\Protocol\Packet\ModalFormRequestPacket;
use Bedriox\Protocol\Packet\ModalFormResponsePacket;
use Bedriox\Protocol\Packet\PacketIds;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ModalFormPacketTest extends TestCase
{
    public function testRequestHasExactVectorAndRoundTrips(): void
    {
        $packet = new ModalFormRequestPacket(42, '{"x":1}');
        self::assertSame("\x2a\x07{\"x\":1}", $packet->encode());
        self::assertEquals($packet, ModalFormRequestPacket::decode($packet->encode()));
        self::assertSame(PacketIds::MODAL_FORM_REQUEST, BedrockPacketCodec::packetId($packet));
    }

    public function testSubmittedResponseHasExactVectorAndRoundTrips(): void
    {
        $packet = new ModalFormResponsePacket(42, 'true', null);
        self::assertSame("\x2a\x01\x04true\x00", $packet->encode());
        self::assertEquals($packet, ModalFormResponsePacket::decode($packet->encode()));
        self::assertSame(PacketIds::MODAL_FORM_RESPONSE, BedrockPacketCodec::packetId($packet));
    }

    public function testCancelledResponseHasExactVectorAndRoundTrips(): void
    {
        $packet = new ModalFormResponsePacket(42, null, ModalFormCancelReason::UserBusy);
        self::assertSame("\x2a\x00\x01\x01", $packet->encode());
        self::assertEquals($packet, ModalFormResponsePacket::decode($packet->encode()));
    }

    public function testUnknownFutureCancelReasonIsPreserved(): void
    {
        $packet = ModalFormResponsePacket::decode("\x01\x00\x01\xff");
        self::assertSame(255, $packet->cancelReason);
    }

    public function testCloseFormIsExactlyEmpty(): void
    {
        $packet = new ClientboundCloseFormPacket();
        self::assertSame('', $packet->encode());
        self::assertEquals($packet, ClientboundCloseFormPacket::decode(''));
        self::assertSame(PacketIds::CLIENTBOUND_CLOSE_FORM, BedrockPacketCodec::packetId($packet));
    }

    public function testOversizedDefinitionsAndResponsesAreRejectedBeforeEncoding(): void
    {
        try {
            new ModalFormRequestPacket(1, str_repeat('x', ModalFormRequestPacket::MAXIMUM_DEFINITION_BYTES + 1));
            self::fail('Oversized form definition was accepted.');
        } catch (InvalidValueException) {
            self::addToAssertionCount(1);
        }

        $this->expectException(InvalidValueException::class);
        new ModalFormResponsePacket(1, str_repeat('x', ModalFormResponsePacket::MAXIMUM_RESPONSE_BYTES + 1), null);
    }

    /** @param class-string<\Throwable> $exception */
    #[DataProvider('malformedResponses')]
    public function testMalformedResponseIsRejected(string $bytes, string $exception): void
    {
        $this->expectException($exception);
        ModalFormResponsePacket::decode($bytes);
    }

    /** @return iterable<string, array{string, class-string<\Throwable>}> */
    public static function malformedResponses(): iterable
    {
        yield 'missing response presence' => ["\x01", BufferUnderflowException::class];
        yield 'invalid response presence' => ["\x01\x02", MalformedDataException::class];
        yield 'truncated response string' => ["\x01\x01\x04tru", BufferUnderflowException::class];
        yield 'missing cancel presence' => ["\x01\x00", BufferUnderflowException::class];
        yield 'invalid cancel presence' => ["\x01\x00\x02", MalformedDataException::class];
        yield 'trailing bytes' => ["\x01\x00\x00\x00", MalformedDataException::class];
    }
}
