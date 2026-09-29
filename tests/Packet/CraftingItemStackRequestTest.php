<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Tests\Packet;

use Bedriox\Protocol\Exception\CodecException;
use Bedriox\Protocol\Packet\AutoCraftRecipeItemStackRequestAction;
use Bedriox\Protocol\Packet\CraftCreativeItemStackRequestAction;
use Bedriox\Protocol\Packet\CraftLoomItemStackRequestAction;
use Bedriox\Protocol\Packet\CraftNonImplementedItemStackRequestAction;
use Bedriox\Protocol\Packet\CraftRecipeItemStackRequestAction;
use Bedriox\Protocol\Packet\CraftRecipeOptionalItemStackRequestAction;
use Bedriox\Protocol\Packet\CraftRepairAndDisenchantItemStackRequestAction;
use Bedriox\Protocol\Packet\CraftResultsItemStackRequestAction;
use Bedriox\Protocol\Packet\CraftingRecipeIngredient;
use Bedriox\Protocol\Packet\FullContainerName;
use Bedriox\Protocol\Packet\ItemStackRequest;
use Bedriox\Protocol\Packet\ItemStackRequestActionType;
use Bedriox\Protocol\Packet\ItemStackRequestPacket;
use Bedriox\Protocol\Packet\ItemStackRequestSlot;
use Bedriox\Protocol\Packet\PlayerAuthInputFlag;
use Bedriox\Protocol\Packet\PlayerAuthInputPacket;
use Bedriox\Protocol\Packet\TakeItemStackRequestAction;
use Bedriox\Protocol\Value\UnsignedLong;
use PHPUnit\Framework\TestCase;

final class CraftingItemStackRequestTest extends TestCase
{
    private const string VECTOR = '010a080a0c07020b0d08030201010f6d696e6563726166743a73746f6e65feff03010003030e6d696e6563726166743a6c6f677302000c0e09010d0f0affffffff0e10feffffff01080f11067374726970650210121113000100ffffffff';

    public function testCompleteCraftingActionFamilyHasExactVectorAndRoundTrips(): void
    {
        $packet = new ItemStackRequestPacket([new ItemStackRequest(5, [
            new CraftRecipeItemStackRequestAction(7, 2),
            new AutoCraftRecipeItemStackRequestAction(8, 3, [
                CraftingRecipeIngredient::item('minecraft:stone'),
                CraftingRecipeIngredient::itemTag('minecraft:logs', 2),
            ]),
            new CraftCreativeItemStackRequestAction(9, 1),
            new CraftRecipeOptionalItemStackRequestAction(10, -1),
            new CraftRepairAndDisenchantItemStackRequestAction(-2, 1, 4),
            new CraftLoomItemStackRequestAction('stripe', 2),
            new CraftNonImplementedItemStackRequestAction(),
            new CraftResultsItemStackRequestAction([], 1),
        ])]);

        self::assertSame(self::VECTOR, bin2hex($packet->encode()));
        self::assertEquals($packet, ItemStackRequestPacket::decode($packet->encode()));
        self::assertSame(12, ItemStackRequestActionType::CraftRecipe->marker());
        self::assertSame(13, ItemStackRequestActionType::AutoCraftRecipe->marker());
        self::assertSame(19, ItemStackRequestActionType::CraftResults->marker());
    }

    public function testEveryCraftingActionTruncationFailsClosed(): void
    {
        $wire = hex2bin(self::VECTOR);
        self::assertIsString($wire);
        for ($length = 0; $length < strlen($wire); ++$length) {
            try {
                ItemStackRequestPacket::decode(substr($wire, 0, $length));
                self::fail("Truncated crafting request was accepted at {$length} bytes.");
            } catch (CodecException) {
                self::addToAssertionCount(1);
            }
        }
    }

    public function testOptionalRecipeWithoutAResultHasExactVectorAndRoundTrips(): void
    {
        $wire = hex2bin('0106010d0f00ffffffff00ffffffff');
        self::assertIsString($wire);

        $packet = ItemStackRequestPacket::decode($wire);
        $action = $packet->requests[0]->actions[0];

        self::assertEquals(new CraftRecipeOptionalItemStackRequestAction(0, -1), $action);
        self::assertSame($wire, $packet->encode());
        for ($length = 0; $length < strlen($wire); ++$length) {
            try {
                ItemStackRequestPacket::decode(substr($wire, 0, $length));
                self::fail("Truncated optional-recipe request was accepted at {$length} bytes.");
            } catch (CodecException) {
                self::addToAssertionCount(1);
            }
        }
    }

    public function testRecipeCraftRequestIsClosedInEmbeddedPlayerInput(): void
    {
        $request = new ItemStackRequest(9, [new CraftRecipeItemStackRequestAction(17, 1)]);
        $packet = new PlayerAuthInputPacket(
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
            9,
            null,
            null,
            $request,
        );

        $decoded = PlayerAuthInputPacket::decode($packet->encode());
        self::assertEquals($request, $decoded->itemStackRequest);
        self::assertSame(
            'e6491082c556c2eb7832ee2233c4a467cd7f9e6d6d162b88b75177a8c85354bb',
            hash('sha256', $packet->encode()),
        );
    }

    public function testCraftingContainerNamesDecodeWithoutRawIds(): void
    {
        $input = new ItemStackRequestSlot(new FullContainerName(FullContainerName::CRAFTING_INPUT), 8, 42);
        $output = new ItemStackRequestSlot(new FullContainerName(FullContainerName::CRAFTING_OUTPUT), 0, 43);
        $created = new ItemStackRequestSlot(new FullContainerName(FullContainerName::CREATED_OUTPUT), 0, 44);

        $packet = new ItemStackRequestPacket([new ItemStackRequest(7, [
            new TakeItemStackRequestAction(1, $input, $created),
            new TakeItemStackRequestAction(1, $output, $created),
        ])]);
        $decoded = ItemStackRequestPacket::decode($packet->encode());

        self::assertSame(
            '010e020000010d00082a0000003c00002c0000000000010e00002b0000003c00002c00000000ffffffff',
            bin2hex($packet->encode()),
        );
        self::assertSame(13, $input->containerName->containerNameId);
        self::assertSame(14, $output->containerName->containerNameId);
        self::assertSame(60, $created->containerName->containerNameId);
        self::assertEquals($packet, $decoded);
        $decodedInput = $decoded->requests[0]->actions[0];
        $decodedOutput = $decoded->requests[0]->actions[1];
        self::assertInstanceOf(TakeItemStackRequestAction::class, $decodedInput);
        self::assertInstanceOf(TakeItemStackRequestAction::class, $decodedOutput);
        self::assertSame(
            FullContainerName::CRAFTING_INPUT,
            $decodedInput->source->containerName->containerNameId,
        );
        self::assertSame(
            FullContainerName::CRAFTING_OUTPUT,
            $decodedOutput->source->containerName->containerNameId,
        );
    }

    public function testOversizedAutoCraftIngredientListIsRejectedBeforeIteration(): void
    {
        $wire = hex2bin('0102010b0d01010a00ffffffff');
        self::assertIsString($wire);
        $this->expectException(CodecException::class);
        ItemStackRequestPacket::decode($wire);
    }
}
