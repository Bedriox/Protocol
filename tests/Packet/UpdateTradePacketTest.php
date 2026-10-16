<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Tests\Packet;

use Bedriox\Protocol\Exception\CodecException;
use Bedriox\Protocol\Exception\InvalidValueException;
use Bedriox\Protocol\Packet\ActorFlag;
use Bedriox\Protocol\Packet\ActorMetadata;
use Bedriox\Protocol\Packet\BedrockPacketCodec;
use Bedriox\Protocol\Packet\ContainerSlotType;
use Bedriox\Protocol\Packet\ContainerType;
use Bedriox\Protocol\Packet\FullContainerName;
use Bedriox\Protocol\Packet\PacketIds;
use Bedriox\Protocol\Packet\UpdateTradePacket;
use Bedriox\Protocol\Packet\VillagerActorMetadata;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class UpdateTradePacketTest extends TestCase
{
    private const string EMPTY_NETWORK_NBT = "\x0a\x00\x00";
    private const string EMPTY_LITTLE_ENDIAN_NBT = "\x0a\x00\x00\x00";
    private const string KNOWN_WIRE = "\x07\x0f\x00\x04\x09\x22\x06Trader\x01\x01\x0a\x00\x00";

    public function testCurrentPacketIdAndKnownWireVector(): void
    {
        $packet = new UpdateTradePacket(7, ContainerType::Trade, 0, 2, -5, 17, 'Trader', true, true, self::EMPTY_NETWORK_NBT);

        self::assertSame(80, PacketIds::UPDATE_TRADE);
        self::assertSame(PacketIds::UPDATE_TRADE, $packet->packetId());
        self::assertSame(self::KNOWN_WIRE, $packet->encode());
        self::assertEquals($packet, UpdateTradePacket::decode(self::KNOWN_WIRE));
        self::assertEquals($packet, BedrockPacketCodec::decode(PacketIds::UPDATE_TRADE, self::KNOWN_WIRE));
        self::assertSame(PacketIds::UPDATE_TRADE, BedrockPacketCodec::packetId($packet));
    }

    public function testLittleEndianOffersAreConvertedToNetworkNbt(): void
    {
        $packet = UpdateTradePacket::fromLittleEndianOffers(4, 0, 8, 9, 'Villager', self::EMPTY_LITTLE_ENDIAN_NBT);

        self::assertSame(ContainerType::Trade, $packet->containerType);
        self::assertSame(0, $packet->size);
        self::assertSame(self::EMPTY_NETWORK_NBT, $packet->offersNetworkNbt);
        self::assertEquals($packet, UpdateTradePacket::decode($packet->encode()));
    }

    public function testEveryTruncationIsRejected(): void
    {
        for ($length = 0; $length < strlen(self::KNOWN_WIRE); ++$length) {
            try {
                UpdateTradePacket::decode(substr(self::KNOWN_WIRE, 0, $length));
                self::fail('Truncated update-trade payload was accepted at length ' . $length . '.');
            } catch (CodecException) {
                self::addToAssertionCount(1);
            }
        }
    }

    #[DataProvider('invalidConstructorValues')]
    public function testInvalidOutboundValuesAreRejected(callable $factory): void
    {
        $this->expectException(InvalidValueException::class);
        $factory();
    }

    /** @return iterable<string, array{callable(): UpdateTradePacket}> */
    public static function invalidConstructorValues(): iterable
    {
        $valid = static fn (
            int $containerId = 1,
            ContainerType $type = ContainerType::Trade,
            int $size = 0,
            int $tier = 0,
            string $name = 'Villager',
            string $nbt = self::EMPTY_NETWORK_NBT,
        ): UpdateTradePacket => new UpdateTradePacket($containerId, $type, $size, $tier, 1, 2, $name, true, true, $nbt);

        yield 'negative container ID' => [static fn (): UpdateTradePacket => $valid(containerId: -1)];
        yield 'container ID overflow' => [static fn (): UpdateTradePacket => $valid(containerId: 256)];
        yield 'wrong container type' => [static fn (): UpdateTradePacket => $valid(type: ContainerType::Horse)];
        yield 'nonzero size' => [static fn (): UpdateTradePacket => $valid(size: 1)];
        yield 'negative tier' => [static fn (): UpdateTradePacket => $valid(tier: -1)];
        yield 'tier overflow' => [static fn (): UpdateTradePacket => $valid(tier: 5)];
        yield 'oversized name' => [static fn (): UpdateTradePacket => $valid(name: str_repeat('a', UpdateTradePacket::MAXIMUM_DISPLAY_NAME_BYTES + 1))];
        yield 'empty offers NBT' => [static fn (): UpdateTradePacket => $valid(nbt: '')];
        yield 'wrong offers root' => [static fn (): UpdateTradePacket => $valid(nbt: "\x01\x00\x00")];
        yield 'oversized offers NBT' => [static fn (): UpdateTradePacket => $valid(nbt: str_repeat("\0", UpdateTradePacket::MAXIMUM_OFFERS_NBT_BYTES + 1))];
    }

    public function testMalformedWireDomainsAreRejected(): void
    {
        $payloads = [
            "\x07\x0c" . substr(self::KNOWN_WIRE, 2),
            "\x07\x0f\x02" . substr(self::KNOWN_WIRE, 3),
            "\x07\x0f\x00\x0a" . substr(self::KNOWN_WIRE, 4),
            substr_replace(self::KNOWN_WIRE, "\x02", 13, 1),
            substr_replace(self::KNOWN_WIRE, "\x02", 14, 1),
            substr(self::KNOWN_WIRE, 0, -3) . "\x01\x00\x00",
            self::KNOWN_WIRE . "\x00",
        ];

        foreach ($payloads as $payload) {
            try {
                UpdateTradePacket::decode($payload);
                self::fail('Malformed update-trade payload was accepted.');
            } catch (CodecException) {
                self::addToAssertionCount(1);
            }
        }
    }

    public function testTradeMetadataAndContainerAliasesUseCurrentIdentifiers(): void
    {
        $metadata = VillagerActorMetadata::tradeState(3, 4, 99, 2, 4, 70, -2, -1, 3, 20);

        self::assertSame(
            [2, 43, 67, 100, 101, 102, 113, 114, 115, 116],
            array_map(static fn (ActorMetadata $entry): int => $entry->id, $metadata),
        );
        self::assertSame(ActorMetadata::TYPE_LONG, $metadata[2]->type);
        self::assertSame(ActorFlag::TradeInterest, ActorFlag::from(76));
        self::assertSame(ContainerSlotType::TradeIngredientOne->value, FullContainerName::TRADE_INGREDIENT_ONE);
        self::assertSame(ContainerSlotType::TradeIngredientTwo->value, FullContainerName::TRADE_INGREDIENT_TWO);
        self::assertSame(ContainerSlotType::TradeResult->value, FullContainerName::TRADE_RESULT);
        self::assertSame(ContainerSlotType::TradeTwoIngredientOne->value, FullContainerName::TRADE_TWO_INGREDIENT_ONE);
        self::assertSame(ContainerSlotType::TradeTwoIngredientTwo->value, FullContainerName::TRADE_TWO_INGREDIENT_TWO);
        self::assertSame(ContainerSlotType::TradeTwoResult->value, FullContainerName::TRADE_TWO_RESULT);
    }
}
