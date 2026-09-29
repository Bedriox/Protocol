<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Tests\Packet;

use Bedriox\Protocol\Exception\CodecException;
use Bedriox\Protocol\Exception\InvalidValueException;
use Bedriox\Protocol\Packet\BedrockPacketCodec;
use Bedriox\Protocol\Packet\BrewingStandProperty;
use Bedriox\Protocol\Packet\ContainerSetDataPacket;
use Bedriox\Protocol\Packet\FurnaceProperty;
use Bedriox\Protocol\Packet\PacketIds;
use PHPUnit\Framework\TestCase;

final class ContainerSetDataPacketTest extends TestCase
{
    private const string VECTOR = '070228';

    public function testBrewingProgressHasExactCurrentVectorAndRegistry(): void
    {
        $packet = ContainerSetDataPacket::brewingStand(7, BrewingStandProperty::FuelAmount, 20);

        self::assertSame(self::VECTOR, bin2hex($packet->encode()));
        self::assertEquals($packet, ContainerSetDataPacket::decode($packet->encode()));
        self::assertEquals($packet, BedrockPacketCodec::decode(PacketIds::CONTAINER_SET_DATA, $packet->encode()));
        self::assertSame(PacketIds::CONTAINER_SET_DATA, BedrockPacketCodec::packetId($packet));
        self::assertSame(51, $packet->packetId());
    }

    public function testTypedBrewingAndFurnacePropertiesCoverCurrentDomain(): void
    {
        self::assertSame([0, 1, 2], array_column(BrewingStandProperty::cases(), 'value'));
        self::assertSame([0, 1, 2, 3, 4], array_column(FurnaceProperty::cases(), 'value'));

        foreach (BrewingStandProperty::cases() as $property) {
            $packet = ContainerSetDataPacket::brewingStand(0xff, $property, -0x80000000);
            self::assertEquals($packet, ContainerSetDataPacket::decode($packet->encode()));
        }
        foreach (FurnaceProperty::cases() as $property) {
            $packet = ContainerSetDataPacket::furnace(0, $property, 0x7fffffff);
            self::assertEquals($packet, ContainerSetDataPacket::decode($packet->encode()));
        }
    }

    public function testEveryTruncationAndTrailingByteFailsClosed(): void
    {
        $wire = hex2bin(self::VECTOR);
        self::assertIsString($wire);
        for ($length = 0; $length < strlen($wire); ++$length) {
            try {
                ContainerSetDataPacket::decode(substr($wire, 0, $length));
                self::fail("Truncated container data was accepted at {$length} bytes.");
            } catch (CodecException) {
                self::addToAssertionCount(1);
            }
        }
        try {
            ContainerSetDataPacket::decode($wire . "\0");
            self::fail('Trailing container-data byte was accepted.');
        } catch (CodecException) {
            self::addToAssertionCount(1);
        }
    }

    public function testConstructorRejectsValuesOutsideWireRanges(): void
    {
        foreach ([
            static fn () => new ContainerSetDataPacket(-1, 0, 0),
            static fn () => new ContainerSetDataPacket(0x100, 0, 0),
            static fn () => new ContainerSetDataPacket(0, 0x80000000, 0),
            static fn () => new ContainerSetDataPacket(0, 0, -0x80000001),
        ] as $invalid) {
            try {
                $invalid();
                self::fail('Invalid container data was accepted.');
            } catch (InvalidValueException) {
                self::addToAssertionCount(1);
            }
        }
    }
}
