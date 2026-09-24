<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Tests\Packet;

use Bedriox\Protocol\Exception\CodecException;
use Bedriox\Protocol\Exception\InvalidValueException;
use Bedriox\Protocol\Packet\CraftCreativeItemStackRequestAction;
use Bedriox\Protocol\Packet\CraftResultsItemStackRequestAction;
use Bedriox\Protocol\Packet\CreateItemStackRequestAction;
use Bedriox\Protocol\Packet\CreativeContentPacket;
use Bedriox\Protocol\Packet\CreativeItemCategory;
use Bedriox\Protocol\Packet\CreativeItemEntry;
use Bedriox\Protocol\Packet\CreativeItemGroup;
use Bedriox\Protocol\Packet\FullContainerName;
use Bedriox\Protocol\Packet\InventoryItemStack;
use Bedriox\Protocol\Packet\ItemStackRequest;
use Bedriox\Protocol\Packet\ItemStackRequestPacket;
use Bedriox\Protocol\Packet\ItemStackRequestResultItem;
use Bedriox\Protocol\Packet\ItemStackRequestSlot;
use Bedriox\Protocol\Packet\PlayerAuthInputFlag;
use Bedriox\Protocol\Packet\PlayerAuthInputPacket;
use Bedriox\Protocol\Packet\TakeItemStackRequestAction;
use Bedriox\Protocol\Value\UnsignedLong;
use PHPUnit\Framework\TestCase;

final class CreativeContentPacketTest extends TestCase
{
    private const string EMPTY_ITEM_USER_DATA = "\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00";
    private const string NAMED_ITEM_USER_DATA = "\xff\xff\x01\x0a\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00";

    public function testCompleteCreativeEntryShapeHasKnownVectorAndRoundTrips(): void
    {
        $first = new InventoryItemStack(101, 1, 7, null, 12_345, self::NAMED_ITEM_USER_DATA);
        $second = new InventoryItemStack(101, 1, 47, null, 54_321, self::EMPTY_ITEM_USER_DATA);
        $packet = new CreativeContentPacket(
            [
                new CreativeItemGroup(
                    CreativeItemCategory::Construction,
                    'itemGroup.name.test',
                    new InventoryItemStack(101, 1, 0, null, 12_345, self::EMPTY_ITEM_USER_DATA),
                ),
                new CreativeItemGroup(
                    CreativeItemCategory::Items,
                    '',
                    new InventoryItemStack(102, 1, 0, null, 0, self::EMPTY_ITEM_USER_DATA),
                ),
            ],
            [
                new CreativeItemEntry(17, $first, 0),
                new CreativeItemEntry(4_000_000_000, $second, 1),
            ],
        );

        self::assertSame(
            '0201136974656d47726f75702e6e616d652e74657374ca01010000f2c0010a000000000000000000000400cc01010000000a000000000000000000000211ca01010007f2c0010effff010a000000000000000000000080d0acf30eca0101002fe2d0060a0000000000000000000001',
            bin2hex($packet->encode()),
        );
        self::assertEquals($packet, CreativeContentPacket::decode($packet->encode()));
        self::assertSame(7, $packet->entries[0]->item->aux);
        self::assertSame(12_345, $packet->entries[0]->item->blockRuntimeId);
        self::assertSame(self::NAMED_ITEM_USER_DATA, $packet->entries[0]->item->userData);
        self::assertSame(4_000_000_000, $packet->entries[1]->networkId);
        $this->assertRejectsEveryTruncation(CreativeContentPacket::decode(...), $packet->encode());
    }

    public function testCreativeSelectionConversationIsClosedInStandaloneAndEmbeddedForms(): void
    {
        $createdOutput = new ItemStackRequestSlot(
            new FullContainerName(FullContainerName::CREATED_OUTPUT),
            50,
            -13,
        );
        $hotbar = new ItemStackRequestSlot(new FullContainerName(FullContainerName::HOTBAR), 1, 0);
        $request = new ItemStackRequest(-13, [
            new CraftCreativeItemStackRequestAction(4_000_000_000, 1),
            new CreateItemStackRequestAction(50),
            new CraftResultsItemStackRequestAction([
                new ItemStackRequestResultItem(
                    1,
                    'minecraft:test_item',
                    47,
                    1,
                    54_321,
                    self::NAMED_ITEM_USER_DATA,
                ),
            ], 1),
            new TakeItemStackRequestAction(1, $createdOutput, $hotbar),
        ]);
        $standalone = new ItemStackRequestPacket([$request]);

        self::assertSame(
            '0119040c0e80d0acf30e010606321113010101136d696e6563726166743a746573745f6974656d5e0100b1a8030effff010a00000000000000000000010000013c0032f3ffffff1c00010000000000ffffffff',
            bin2hex($standalone->encode()),
        );
        self::assertEquals($standalone, ItemStackRequestPacket::decode($standalone->encode()));

        $embedded = new PlayerAuthInputPacket(
            0.0,
            0.0,
            0.0,
            65.621,
            0.0,
            0.0,
            0.0,
            0.0,
            [PlayerAuthInputFlag::PerformItemStackRequest->value],
            1,
            0,
            0,
            0.0,
            0.0,
            UnsignedLong::fromInt(20),
            0.0,
            0.0,
            0.0,
            0.0,
            0.0,
            0.0,
            0.0,
            0.0,
            0.0,
            0.0,
            true,
            -13,
            null,
            null,
            $request,
        );
        $decoded = PlayerAuthInputPacket::decode($embedded->encode());

        self::assertEquals($request, $decoded->itemStackRequest);
        self::assertSame('cde85ebbf4dd84f854f56f513229164539ee96f3233b3315d9d6910ec2580220', hash('sha256', $embedded->encode()));
        $this->assertRejectsEveryTruncation(PlayerAuthInputPacket::decode(...), $embedded->encode());
    }

    public function testCreativeContentRejectsDuplicateIdsAndExcessiveCollections(): void
    {
        $item = new InventoryItemStack(5, 1, 0, null, 0, self::EMPTY_ITEM_USER_DATA);
        $group = new CreativeItemGroup(CreativeItemCategory::Items, 'items', $item);

        foreach ([
            static fn () => new CreativeContentPacket(
                [$group],
                [new CreativeItemEntry(1, $item, 0), new CreativeItemEntry(1, $item, 0)],
            ),
            static fn () => new CreativeContentPacket(
                array_fill(0, CreativeContentPacket::MAXIMUM_GROUPS + 1, $group),
            ),
            static fn () => new CreativeContentPacket(
                [$group],
                array_fill(
                    0,
                    CreativeContentPacket::MAXIMUM_ENTRIES + 1,
                    new CreativeItemEntry(1, $item, 0),
                ),
            ),
        ] as $construct) {
            try {
                $construct();
                self::fail('Invalid creative content was accepted.');
            } catch (InvalidValueException) {
                self::addToAssertionCount(1);
            }
        }
    }

    public function testCreativeContentRejectsAdversarialWireCountsAndUserData(): void
    {
        foreach ([
            "\x81\x02",
            "\x00\x81\x80\x04",
            "\x01\x07",
            str_repeat("\x00", 4_194_305),
        ] as $wire) {
            try {
                CreativeContentPacket::decode($wire);
                self::fail('Adversarial creative content was accepted.');
            } catch (CodecException) {
                self::addToAssertionCount(1);
            }
        }

        $this->expectException(InvalidValueException::class);
        new InventoryItemStack(5, 1, 0, null, 0, str_repeat('x', InventoryItemStack::MAXIMUM_USER_DATA_BYTES + 1));
    }

    /** @param callable(string): object $decode */
    private function assertRejectsEveryTruncation(callable $decode, string $wire): void
    {
        for ($length = 0; $length < strlen($wire); ++$length) {
            try {
                $decode(substr($wire, 0, $length));
                self::fail("Truncated packet was accepted at {$length} bytes.");
            } catch (CodecException) {
                self::addToAssertionCount(1);
            }
        }
        try {
            $decode($wire . "\0");
            self::fail('Packet accepted trailing data.');
        } catch (CodecException) {
            self::addToAssertionCount(1);
        }
    }
}
