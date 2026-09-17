<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Tests\Packet;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Bedriox\Protocol\Exception\InvalidValueException;
use Bedriox\Protocol\Packet\BlockPropertyData;
use Bedriox\Protocol\Packet\ExperimentData;
use Bedriox\Protocol\Packet\GameRuleSet;
use Bedriox\Protocol\Packet\StartGamePacket;
use Bedriox\Protocol\Value\UnsignedLong;

final class StartGameDataDrivenTest extends TestCase
{
    private const string EMPTY_LITTLE_ENDIAN_ROOT = "\x0a\x00\x00\x00";

    public function testDataDrivenPropertyAndRequiredExperimentsUseKnownWireOrderAndMarkExperimentsToggled(): void
    {
        $packet = StartGamePacket::fixedFlat(
            1,
            UnsignedLong::fromInt(2),
            0.5,
            65.0,
            -0.5,
            'level',
            'Flat',
            gameRules: new GameRuleSet([]),
            blockProperties: [BlockPropertyData::fromLittleEndianNbt('minecraft:test', self::EMPTY_LITTLE_ENDIAN_ROOT)],
        );

        self::assertSame(
            '0202000000003f00008242000000bf00000000000000000000000000000000000006706c61696e730002000000008001000100000001000000000000000000000000010108080000000300000011646174615f64726976656e5f6974656d7301197570636f6d696e675f63726561746f725f6665617475726573011c6578706572696d656e74616c5f6d6f6c616e675f66656174757265730101000001040000000000000000000000000007312e32362e353010000000100000000000000000000000056c6576656c04466c617400000001000000000000000000010e6d696e6563726166743a746573740a00000001000a00000000000000000000000000000000000000000000000000000000010000000000',
            bin2hex($packet->encode()),
        );
    }

    public function testBlockPropertyConvertsLittleEndianNbtToNetworkNbt(): void
    {
        $property = BlockPropertyData::fromLittleEndianNbt('minecraft:test', self::EMPTY_LITTLE_ENDIAN_ROOT);

        self::assertSame('minecraft:test', $property->name);
        self::assertSame("\x0a\x00\x00", $property->networkNbt);
    }

    /** @return iterable<string, array{callable(): mixed}> */
    public static function invalidPropertyProvider(): iterable
    {
        yield 'identifier has no namespace' => [static fn (): BlockPropertyData => BlockPropertyData::fromLittleEndianNbt('test', self::EMPTY_LITTLE_ENDIAN_ROOT)];
        yield 'truncated little endian NBT' => [static fn (): BlockPropertyData => BlockPropertyData::fromLittleEndianNbt('minecraft:test', "\x0a\x00")];
        yield 'little endian NBT has wrong root type' => [static fn (): BlockPropertyData => BlockPropertyData::fromLittleEndianNbt('minecraft:test', "\x09\x00\x00\x00\x00\x00\x00\x00")];
        yield 'little endian NBT is oversized' => [static fn (): BlockPropertyData => BlockPropertyData::fromLittleEndianNbt('minecraft:test', "\x0a" . str_repeat("\0", 262_144))];
    }

    #[DataProvider('invalidPropertyProvider')]
    public function testInvalidBlockPropertyIsRejected(callable $operation): void
    {
        $this->expectException(InvalidValueException::class);
        $operation();
    }

    /** @return iterable<string, array{string}> */
    public static function invalidExperimentNameProvider(): iterable
    {
        yield 'empty' => [''];
        yield 'oversized' => [str_repeat('x', 257)];
    }

    #[DataProvider('invalidExperimentNameProvider')]
    public function testExperimentNamesAreBounded(string $name): void
    {
        $this->expectException(InvalidValueException::class);
        new ExperimentData($name, true);
    }

    public function testStartGameRejectsInvalidAndDuplicateLists(): void
    {
        $property = BlockPropertyData::fromLittleEndianNbt('minecraft:test', self::EMPTY_LITTLE_ENDIAN_ROOT);

        foreach (
            [
                ['blockProperties' => [$property, $property]],
                ['experiments' => [new ExperimentData('test', true), new ExperimentData('test', false)]],
            ] as $arguments
        ) {
            try {
                StartGamePacket::fixedFlat(...array_merge([
                    'uniqueEntityId' => 1,
                    'runtimeEntityId' => UnsignedLong::fromInt(2),
                    'x' => 0.5,
                    'y' => 65.0,
                    'z' => -0.5,
                    'levelId' => 'level',
                    'levelName' => 'Flat',
                    'gameRules' => new GameRuleSet([]),
                ], $arguments));
                self::fail('Invalid StartGame data-driven list was accepted.');
            } catch (InvalidValueException) {
                self::addToAssertionCount(1);
            }
        }
    }

    public function testStartGameRejectsOversizedPropertyCount(): void
    {
        $property = BlockPropertyData::fromLittleEndianNbt('minecraft:test', self::EMPTY_LITTLE_ENDIAN_ROOT);

        $this->expectException(InvalidValueException::class);
        StartGamePacket::fixedFlat(
            1,
            UnsignedLong::fromInt(2),
            0.5,
            65.0,
            -0.5,
            'level',
            'Flat',
            blockProperties: array_fill(0, 1_025, $property),
        );
    }
}
