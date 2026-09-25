<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Tests\Packet;

use PHPUnit\Framework\TestCase;
use Bedriox\Protocol\Exception\CodecException;
use Bedriox\Protocol\Exception\InvalidValueException;
use Bedriox\Protocol\Packet\BedrockPacketCodec;
use Bedriox\Protocol\Packet\FullContainerName;
use Bedriox\Protocol\Packet\ItemStackRequest;
use Bedriox\Protocol\Packet\ItemStackRequestSlot;
use Bedriox\Protocol\Packet\ItemStackResponse;
use Bedriox\Protocol\Packet\ItemStackResponseContainer;
use Bedriox\Protocol\Packet\ItemStackResponsePacket;
use Bedriox\Protocol\Packet\ItemStackResponseSlot;
use Bedriox\Protocol\Packet\ItemStackRequestPacket;
use Bedriox\Protocol\Packet\PacketIds;
use Bedriox\Protocol\Packet\PlaceItemStackRequestAction;
use Bedriox\Protocol\Packet\SwapItemStackRequestAction;
use Bedriox\Protocol\Packet\TakeItemStackRequestAction;

final class ItemStackResponsePacketTest extends TestCase
{
    public function testCurrentInventoryContainerIdsMatchProtocol2193(): void
    {
        self::assertSame(6, FullContainerName::ARMOR);
        self::assertSame(12, FullContainerName::COMBINED_HOTBAR_AND_INVENTORY);
        self::assertSame(28, FullContainerName::HOTBAR);
        self::assertSame(29, FullContainerName::INVENTORY);
        self::assertSame(34, FullContainerName::OFFHAND);
        self::assertSame(59, FullContainerName::CURSOR);
        self::assertSame(60, FullContainerName::CREATED_OUTPUT);
    }

    public function testCurrentErrorResponseHasNoContainerMutation(): void
    {
        $packet = new ItemStackResponsePacket([7, -2]);
        self::assertSame(PacketIds::ITEM_STACK_RESPONSE, BedrockPacketCodec::packetId($packet));
        self::assertSame('02010e00010300', bin2hex($packet->encode()));
        self::assertSame($packet->encode(), BedrockPacketCodec::encode($packet));
    }

    public function testResponseCountIsBounded(): void
    {
        $this->expectException(InvalidValueException::class);
        new ItemStackResponsePacket(array_fill(0, 129, 1));
    }

    public function testStandaloneRequestBatchProjectsIdsForRejection(): void
    {
        $normalized = new ItemStackRequestPacket([7, -2]);
        self::assertSame(PacketIds::ITEM_STACK_REQUEST, BedrockPacketCodec::packetId($normalized));
        self::assertEquals($normalized, BedrockPacketCodec::decode(PacketIds::ITEM_STACK_REQUEST, $normalized->encode()));

        $slot = "\x1d\0\0" . pack('V', 0);
        $withTake = "\1\x0e\1\0\0\1{$slot}{$slot}\0\xff\xff\xff\xff";
        $decoded = BedrockPacketCodec::decode(PacketIds::ITEM_STACK_REQUEST, $withTake);
        self::assertInstanceOf(ItemStackRequestPacket::class, $decoded);
        self::assertSame([7], $decoded->requestIds);
    }

    public function testTakePlaceAndSwapRequestsRetainEveryAuthoritativeHint(): void
    {
        $inventory = new ItemStackRequestSlot(new FullContainerName(FullContainerName::INVENTORY), 0, 17);
        $cursor = new ItemStackRequestSlot(new FullContainerName(FullContainerName::CURSOR), 0, 0);
        $request = new ItemStackRequest(5, [
            new TakeItemStackRequestAction(32, $inventory, $cursor),
            new PlaceItemStackRequestAction(16, $cursor, $inventory),
            new SwapItemStackRequestAction($inventory, $cursor),
        ]);
        $packet = new ItemStackRequestPacket([$request]);

        self::assertSame(
            '010a030000201d0000110000003b0000000000000101103b0000000000001d00001100000002021d0000110000003b00000000000000ffffffff',
            bin2hex($packet->encode()),
        );
        self::assertEquals($packet, ItemStackRequestPacket::decode($packet->encode()));
    }

    public function testSuccessResponseCarriesAuthoritativeAffectedSlots(): void
    {
        $response = new ItemStackResponse(ItemStackResponse::STATUS_SUCCESS, 5, [
            new ItemStackResponseContainer(new FullContainerName(FullContainerName::INVENTORY), [
                new ItemStackResponseSlot(0, 0, 32, 18),
                new ItemStackResponseSlot(1, 1, 32, 19, 'named', 'safe', 7),
            ]),
        ]);
        $packet = new ItemStackResponsePacket([$response]);

        self::assertSame(
            '01000a01011d000200002001240000000101200126056e616d65640104736166650e',
            bin2hex($packet->encode()),
        );
        self::assertEquals($packet, ItemStackResponsePacket::decode($packet->encode()));
    }

    public function testEmptyAuthoritativeSlotOmitsTheStackNetworkId(): void
    {
        $packet = new ItemStackResponsePacket([new ItemStackResponse(
            ItemStackResponse::STATUS_SUCCESS,
            -1055,
            [new ItemStackResponseContainer(new FullContainerName(FullContainerName::LEVEL_ENTITY), [
                new ItemStackResponseSlot(12, 12, 0, null),
            ])],
        )]);

        self::assertSame('0100bd1001010700010c0c0000000000', bin2hex($packet->encode()));
        self::assertEquals($packet, ItemStackResponsePacket::decode($packet->encode()));
    }

    public function testTypedRequestAndSuccessResponseRejectEveryTruncationAndTrailingData(): void
    {
        $inventory = new ItemStackRequestSlot(new FullContainerName(FullContainerName::INVENTORY), 0, 17);
        $cursor = new ItemStackRequestSlot(new FullContainerName(FullContainerName::CURSOR), 0, 0);
        $requestWire = (new ItemStackRequestPacket([new ItemStackRequest(5, [
            new TakeItemStackRequestAction(32, $inventory, $cursor),
            new PlaceItemStackRequestAction(16, $cursor, $inventory),
            new SwapItemStackRequestAction($inventory, $cursor),
        ])]))->encode();
        $responseWire = (new ItemStackResponsePacket([new ItemStackResponse(
            ItemStackResponse::STATUS_SUCCESS,
            5,
            [new ItemStackResponseContainer(new FullContainerName(FullContainerName::INVENTORY), [
                new ItemStackResponseSlot(0, 0, 32, 18),
                new ItemStackResponseSlot(1, 1, 32, 19, 'named', 'safe', 7),
            ])],
        )]))->encode();

        foreach ([
            [ItemStackRequestPacket::class, $requestWire],
            [ItemStackResponsePacket::class, $responseWire],
        ] as [$packetClass, $wire]) {
            for ($length = 0; $length < strlen($wire); ++$length) {
                try {
                    $packetClass::decode(substr($wire, 0, $length));
                    self::fail("Truncated {$packetClass} was accepted at {$length} bytes.");
                } catch (CodecException) {
                    self::addToAssertionCount(1);
                }
            }
            try {
                $packetClass::decode($wire . "\0");
                self::fail("{$packetClass} accepted trailing data.");
            } catch (CodecException) {
                self::addToAssertionCount(1);
            }
        }
    }
}
