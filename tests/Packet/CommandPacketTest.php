<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Tests\Packet;

use Bedriox\Protocol\Exception\InvalidValueException;
use Bedriox\Protocol\Exception\MalformedDataException;
use Bedriox\Protocol\Exception\CodecException;
use Bedriox\Protocol\Packet\AvailableCommandsPacket;
use Bedriox\Protocol\Packet\BedrockPacketCodec;
use Bedriox\Protocol\Packet\CommandArgumentType;
use Bedriox\Protocol\Packet\CommandDefinition;
use Bedriox\Protocol\Packet\CommandOrigin;
use Bedriox\Protocol\Packet\CommandOriginType;
use Bedriox\Protocol\Packet\CommandOutputMessage;
use Bedriox\Protocol\Packet\CommandOutputPacket;
use Bedriox\Protocol\Packet\CommandOutputType;
use Bedriox\Protocol\Packet\CommandOverload;
use Bedriox\Protocol\Packet\CommandParameter;
use Bedriox\Protocol\Packet\CommandPermission;
use Bedriox\Protocol\Packet\CommandRequestPacket;
use Bedriox\Protocol\Packet\PacketIds;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CommandPacketTest extends TestCase
{
    private const string NIL_UUID = '00000000-0000-0000-0000-000000000000';

    public function testCommandRequestMatchesCurrentProtocolVector(): void
    {
        $packet = new CommandRequestPacket(
            '/version',
            new CommandOrigin(CommandOriginType::Player, self::NIL_UUID, 'abc'),
        );
        $wire = hex2bin('082f76657273696f6e00' . str_repeat('00', 16) . '0361626300066c6174657374');
        self::assertIsString($wire);
        self::assertSame($wire, $packet->encode());
        self::assertEquals($packet, CommandRequestPacket::decode($wire));
        self::assertEquals($packet, BedrockPacketCodec::decode(PacketIds::COMMAND_REQUEST, $wire));
    }

    public function testDevelopmentConsoleOriginCarriesSignedPlayerId(): void
    {
        $packet = new CommandRequestPacket(
            '/stop',
            new CommandOrigin(CommandOriginType::DevConsole, self::NIL_UUID, '', 42),
            true,
        );
        self::assertEquals($packet, CommandRequestPacket::decode($packet->encode()));
    }

    public function testAvailableCommandsMatchesCurrentProtocolVector(): void
    {
        $packet = new AvailableCommandsPacket([
            new CommandDefinition('version', 'Show version', CommandPermission::Any, [
                new CommandOverload([new CommandParameter('args', CommandArgumentType::RawText, true)]),
            ]),
        ]);
        $wire = hex2bin(
            '000000000001' .
            '0776657273696f6e0c53686f772076657273696f6e000003616e79ffffffff00' .
            '01000104617267734600100001000000',
        );
        self::assertIsString($wire);
        self::assertSame($wire, $packet->encode());
        self::assertEquals($packet, AvailableCommandsPacket::decode($wire));
        self::assertSame(PacketIds::AVAILABLE_COMMANDS, BedrockPacketCodec::packetId($packet));
    }

    public function testCommandOutputRoundTripsAllCurrentNamedTypes(): void
    {
        $origin = new CommandOrigin(CommandOriginType::Player, self::NIL_UUID, 'request');
        foreach (CommandOutputType::cases() as $type) {
            $packet = new CommandOutputPacket(
                $origin,
                $type,
                1,
                [new CommandOutputMessage('bedriox.command.version', false, ['Bedriox', '1.0.0'])],
                $type === CommandOutputType::DataSet ? '{"ok":true}' : null,
            );
            self::assertEquals($packet, BedrockPacketCodec::decode(PacketIds::COMMAND_OUTPUT, $packet->encode()));
            self::assertSame(PacketIds::COMMAND_OUTPUT, BedrockPacketCodec::packetId($packet));
        }
    }

    /** @return iterable<string, array{callable(): void}> */
    public static function invalidModelProvider(): iterable
    {
        yield 'duplicate command names' => [static function (): void {
            $definition = new CommandDefinition('version', 'Version');
            new AvailableCommandsPacket([$definition, $definition]);
        }];
        yield 'invalid command name' => [static function (): void {
            new CommandDefinition('Bad Name', 'Invalid');
        }];
        yield 'non-origin player id' => [static function (): void {
            new CommandOrigin(CommandOriginType::Player, self::NIL_UUID, '', 1);
        }];
    }

    #[DataProvider('invalidModelProvider')]
    public function testInvalidModelsAreRejected(callable $operation): void
    {
        $this->expectException(InvalidValueException::class);
        $operation();
    }

    public function testMalformedFiniteValuesAndTrailingDataAreRejected(): void
    {
        $originPrefix = "\xff\x01";
        $this->expectException(MalformedDataException::class);
        CommandRequestPacket::decode("\x00" . $originPrefix);
    }

    public function testTruncatedPacketsAreRejectedAtEveryBoundary(): void
    {
        $packets = [
            new CommandRequestPacket('/version', new CommandOrigin(CommandOriginType::Player, self::NIL_UUID, 'id')),
            new AvailableCommandsPacket([new CommandDefinition('version', 'Version', overloads: [new CommandOverload()])]),
            new CommandOutputPacket(new CommandOrigin(CommandOriginType::Player, self::NIL_UUID, 'id'), CommandOutputType::AllOutput, 0),
        ];
        foreach ($packets as $packet) {
            $wire = $packet->encode();
            for ($length = 0; $length < strlen($wire); ++$length) {
                try {
                    BedrockPacketCodec::decode($packet->packetId(), substr($wire, 0, $length));
                    self::fail('Truncated command packet was accepted at length ' . $length);
                } catch (CodecException) {
                    self::addToAssertionCount(1);
                }
            }
        }
    }
}
