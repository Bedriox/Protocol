<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Tests\Packet;

use Bedriox\Protocol\Exception\CodecException;
use Bedriox\Protocol\Exception\InvalidValueException;
use Bedriox\Protocol\Packet\BedrockPacketCodec;
use Bedriox\Protocol\Packet\ContainerMixData;
use Bedriox\Protocol\Packet\CraftingDataPacket;
use Bedriox\Protocol\Packet\CraftingRecipeIngredient;
use Bedriox\Protocol\Packet\InventoryItemStack;
use Bedriox\Protocol\Packet\SmithingTransformRecipe;
use Bedriox\Protocol\Packet\SmithingTrimRecipe;
use Bedriox\Protocol\Packet\CraftingRecipeType;
use Bedriox\Protocol\Packet\CraftingRecipeUnlockRequirement;
use Bedriox\Protocol\Packet\MultiCraftingRecipe;
use Bedriox\Protocol\Packet\PacketIds;
use Bedriox\Protocol\Packet\PotionMixData;
use Bedriox\Protocol\Packet\ShapedCraftingRecipe;
use Bedriox\Protocol\Packet\ShapelessCraftingRecipe;
use PHPUnit\Framework\TestCase;

final class CraftingDataPacketTest extends TestCase
{
    public function testSmithingSectionsRoundTrip(): void
    {
        $ingredient = CraftingRecipeIngredient::item('minecraft:iron_ingot');
        $packet = new CraftingDataPacket(
            smithingTransformRecipes: [new SmithingTransformRecipe(
                'minecraft:upgrade', $ingredient, $ingredient, $ingredient,
                new InventoryItemStack(1, 1, 0, null, 0, ''), 'smithing_table', 100,
            )],
            smithingTrimRecipes: [new SmithingTrimRecipe(
                'minecraft:trim', $ingredient, $ingredient, $ingredient, 'smithing_table', 101,
            )],
        );

        self::assertEquals($packet, CraftingDataPacket::decode($packet->encode()));
    }

    private const string UUID = '00112233-4455-6677-8899-aabbccddeeff';
    private const string VECTOR = '010c62656472696f783a7465737402020101046e616d650f6d696e6563726166743a73746f6e65feff0302010a04000000007766554433221100ffeeddccbbaa99880e6372616674696e675f7461626c6504000100010007010b62656472696f783a6d69780101086974656d5f7461670e6d696e6563726166743a6c6f6773feff0304010a04000000007766554433221100ffeeddccbbaa99880e6372616674696e675f7461626c65000100010008017766554433221100ffeeddccbbaa998809000000000000000001';
    private const string MIX_VECTOR = '000000000000000001020406080a0c010e10120001';

    public function testShapedShapelessAndMultiRecipesHaveExactCurrentVector(): void
    {
        $packet = self::packet();
        self::assertSame(self::VECTOR, bin2hex($packet->encode()));
        self::assertEquals($packet, CraftingDataPacket::decode($packet->encode()));
        self::assertEquals($packet, BedrockPacketCodec::decode(PacketIds::CRAFTING_DATA, $packet->encode()));
        self::assertSame(PacketIds::CRAFTING_DATA, BedrockPacketCodec::packetId($packet));
    }

    public function testEveryTruncationAndTrailingDataFailClosed(): void
    {
        $wire = hex2bin(self::VECTOR);
        self::assertIsString($wire);
        for ($length = 0; $length < strlen($wire); ++$length) {
            try {
                CraftingDataPacket::decode(substr($wire, 0, $length));
                self::fail("Truncated crafting data was accepted at {$length} bytes.");
            } catch (CodecException) {
                self::addToAssertionCount(1);
            }
        }

        $this->expectException(CodecException::class);
        CraftingDataPacket::decode($wire . "\0");
    }

    public function testAllCraftingGridRecipeSectionsRoundTrip(): void
    {
        $output = new InventoryItemStack(5, 1, 0, null, 0, '');
        $unlock = CraftingRecipeUnlockRequirement::none();
        $recipes = [
            new ShapedCraftingRecipe(CraftingRecipeType::ShapedChemistry, 'c:s', 1, 1, [
                CraftingRecipeIngredient::item('minecraft:stone'),
            ], [$output], self::UUID, 'chemistry_table', 0, true, $unlock, 1),
            new ShapelessCraftingRecipe(CraftingRecipeType::UserDataShapeless, 'c:u', [
                CraftingRecipeIngredient::item('minecraft:shulker_box'),
            ], [$output], self::UUID, 'shulker_box', 0, $unlock, 2),
            new ShapelessCraftingRecipe(CraftingRecipeType::ShapelessChemistry, 'c:c', [
                CraftingRecipeIngredient::itemTag('minecraft:logs'),
            ], [$output], self::UUID, 'chemistry_table', 0, $unlock, 3),
        ];
        $packet = new CraftingDataPacket($recipes, false);

        self::assertEquals($packet, CraftingDataPacket::decode($packet->encode()));
    }

    public function testPotionAndContainerMixRegistriesHaveExactCurrentVector(): void
    {
        $packet = new CraftingDataPacket(
            cleanRecipes: true,
            potionMixData: [new PotionMixData(1, 2, 3, 4, 5, 6)],
            containerMixData: [new ContainerMixData(7, 8, 9)],
        );

        self::assertSame(self::MIX_VECTOR, bin2hex($packet->encode()));
        self::assertEquals($packet, CraftingDataPacket::decode($packet->encode()));

        $wire = hex2bin(self::MIX_VECTOR);
        self::assertIsString($wire);
        for ($length = 0; $length < strlen($wire); ++$length) {
            try {
                CraftingDataPacket::decode(substr($wire, 0, $length));
                self::fail("Truncated crafting mix data was accepted at {$length} bytes.");
            } catch (CodecException) {
                self::addToAssertionCount(1);
            }
        }
    }

    public function testCountsDeferredSectionsAndNetworkIdentityAreBounded(): void
    {
        foreach ([
            "\x81\x40",
            "\0\0\0\0\0\0\1",
        ] as $wire) {
            try {
                CraftingDataPacket::decode($wire);
                self::fail('Malformed crafting registry was accepted.');
            } catch (CodecException) {
                self::addToAssertionCount(1);
            }
        }

        $recipe = new MultiCraftingRecipe(self::UUID, 7);
        $this->expectException(InvalidValueException::class);
        new CraftingDataPacket([$recipe, $recipe]);
    }

    public function testConstructorRejectsInvalidRecipeListsAndGridShapes(): void
    {
        $output = new InventoryItemStack(5, 1, 0, null, 0, '');
        foreach ([
            static fn () => new CraftingDataPacket(array_fill(0, CraftingDataPacket::MAXIMUM_RECIPES + 1, new MultiCraftingRecipe(self::UUID, 1))),
            static fn () => new ShapedCraftingRecipe(
                CraftingRecipeType::Shaped,
                'invalid',
                2,
                2,
                [CraftingRecipeIngredient::item('minecraft:stone')],
                [$output],
                self::UUID,
                'crafting_table',
                0,
                false,
                CraftingRecipeUnlockRequirement::none(),
                1,
            ),
            static fn () => new CraftingDataPacket(
                potionMixData: array_fill(
                    0,
                    CraftingDataPacket::MAXIMUM_RECIPES + 1,
                    new PotionMixData(1, 0, 2, 0, 3, 0),
                ),
            ),
            static fn () => (new \ReflectionClass(CraftingDataPacket::class))->newInstance(
                [],
                true,
                [new \stdClass()],
            ),
            static fn () => new PotionMixData(0x80000000, 0, 0, 0, 0, 0),
            static fn () => new ContainerMixData(0, -0x80000001, 0),
        ] as $invalid) {
            try {
                $invalid();
                self::fail('Invalid crafting data was accepted.');
            } catch (InvalidValueException) {
                self::addToAssertionCount(1);
            }
        }
    }

    private static function packet(): CraftingDataPacket
    {
        $output = new InventoryItemStack(5, 4, 0, null, 0, '');
        return new CraftingDataPacket([
            new ShapedCraftingRecipe(
                CraftingRecipeType::Shaped,
                'bedriox:test',
                1,
                1,
                [CraftingRecipeIngredient::item('minecraft:stone')],
                [$output],
                self::UUID,
                'crafting_table',
                2,
                false,
                CraftingRecipeUnlockRequirement::none(),
                7,
            ),
            new ShapelessCraftingRecipe(
                CraftingRecipeType::Shapeless,
                'bedriox:mix',
                [CraftingRecipeIngredient::itemTag('minecraft:logs', 2)],
                [$output],
                self::UUID,
                'crafting_table',
                0,
                CraftingRecipeUnlockRequirement::none(),
                8,
            ),
            new MultiCraftingRecipe(self::UUID, 9),
        ]);
    }
}
