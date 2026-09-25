<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Tests\Packet;

use PHPUnit\Framework\TestCase;
use Bedriox\Protocol\Exception\CodecException;
use Bedriox\Protocol\Exception\InvalidValueException;
use Bedriox\Protocol\Packet\BedrockPacketCodec;
use Bedriox\Protocol\Packet\BlockPosition;
use Bedriox\Protocol\Packet\ContainerOpenPacket;
use Bedriox\Protocol\Packet\ContainerType;
use Bedriox\Protocol\Packet\PacketIds;

final class ContainerOpenPacketTest extends TestCase
{
    private const string VECTOR = '05ff027f040d';
    private const string MAIN_INVENTORY_VECTOR = '2aff000000d804';

    public function testIndependentLiteralVectorRoundTripsAndIsRegistered(): void
    {
        $packet = new ContainerOpenPacket(
            5,
            ContainerType::Inventory,
            new BlockPosition(1, -64, 2),
            -7,
        );

        self::assertSame(self::VECTOR, bin2hex($packet->encode()));
        self::assertEquals($packet, BedrockPacketCodec::decode(PacketIds::CONTAINER_OPEN, $packet->encode()));
        self::assertSame(PacketIds::CONTAINER_OPEN, BedrockPacketCodec::packetId($packet));
    }

    public function testMainPlayerInventoryFactoryUsesDynamicIdAndAuthoritativeActor(): void
    {
        $packet = ContainerOpenPacket::mainPlayerInventory(42, 300);

        self::assertSame(42, $packet->containerId);
        self::assertSame(ContainerType::Inventory, $packet->containerType);
        self::assertEquals(new BlockPosition(0, 0, 0), $packet->position);
        self::assertSame(300, $packet->actorUniqueId);
        self::assertSame(self::MAIN_INVENTORY_VECTOR, bin2hex($packet->encode()));
    }

    public function testWorkbenchOpenUsesTheExistingContainerConversation(): void
    {
        $packet = new ContainerOpenPacket(9, ContainerType::Workbench, new BlockPosition(2, 64, -3), 0);

        self::assertSame('09010480010500', bin2hex($packet->encode()));
        self::assertEquals($packet, ContainerOpenPacket::decode($packet->encode()));
    }

    public function testEveryTruncationAndTrailingByteFailClosed(): void
    {
        $wire = hex2bin(self::VECTOR);
        self::assertIsString($wire);
        for ($length = 0; $length < strlen($wire); ++$length) {
            try {
                ContainerOpenPacket::decode(substr($wire, 0, $length));
                self::fail("Truncated ContainerOpen payload was accepted at {$length} bytes.");
            } catch (CodecException) {
                self::addToAssertionCount(1);
            }
        }

        $this->expectException(CodecException::class);
        ContainerOpenPacket::decode($wire . "\0");
    }

    public function testUnknownSignedContainerTypesFailClosed(): void
    {
        foreach ([0xf8, 0xfe, 0x25, 0x7f] as $typeByte) {
            try {
                ContainerOpenPacket::decode("\0" . chr($typeByte));
                self::fail('Unknown container type was accepted.');
            } catch (CodecException) {
                self::addToAssertionCount(1);
            }
        }
    }

    public function testEveryKnownContainerTypeAndScalarBoundaryRoundTrips(): void
    {
        foreach (ContainerType::cases() as $containerType) {
            $packet = new ContainerOpenPacket(
                0xff,
                $containerType,
                new BlockPosition(-0x80000000, 0x7fffffff, 0),
                $containerType === ContainerType::None ? PHP_INT_MIN : PHP_INT_MAX,
            );
            self::assertEquals($packet, ContainerOpenPacket::decode($packet->encode()));
        }
    }

    public function testContainerIdRangeIsValidatedForConstructorAndFactory(): void
    {
        foreach ([
            static fn () => new ContainerOpenPacket(-1, ContainerType::Inventory, new BlockPosition(0, 0, 0), 0),
            static fn () => new ContainerOpenPacket(256, ContainerType::Inventory, new BlockPosition(0, 0, 0), 0),
            static fn () => ContainerOpenPacket::mainPlayerInventory(-1, 0),
            static fn () => ContainerOpenPacket::mainPlayerInventory(256, 0),
        ] as $createInvalid) {
            try {
                $createInvalid();
                self::fail('Out-of-range container ID was accepted.');
            } catch (InvalidValueException) {
                self::addToAssertionCount(1);
            }
        }
    }
}
