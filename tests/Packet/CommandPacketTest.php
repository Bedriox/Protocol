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
use Bedriox\Protocol\Packet\CommandEnum;
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
use Bedriox\Protocol\Packet\SoftEnumUpdateType;
use Bedriox\Protocol\Packet\UpdateSoftEnumPacket;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CommandPacketTest extends TestCase
{
    private const string NIL_UUID = '00000000-0000-0000-0000-000000000000';
    private const string VECTOR_UUID = '00112233-4455-6677-8899-aabbccddeeff';

    public function testCommandRequestMatchesCurrentProtocolVector(): void
    {
        $packet = new CommandRequestPacket(
            '/version',
            new CommandOrigin(CommandOriginType::Player, self::VECTOR_UUID, 'req-42', 0x0102030405060708),
        );
        $wire = hex2bin(
            '082f76657273696f6e' .
            '06706c61796572' .
            '7766554433221100ffeeddccbbaa9988' .
            '067265712d3432' .
            '0807060504030201' .
            '00' .
            '066c6174657374',
        );
        self::assertIsString($wire);
        self::assertSame($wire, $packet->encode());
        self::assertEquals($packet, CommandRequestPacket::decode($wire));
        self::assertEquals($packet, BedrockPacketCodec::decode(PacketIds::COMMAND_REQUEST, $wire));
    }

    public function testEveryOriginCarriesSignedPlayerId(): void
    {
        foreach ([CommandOriginType::Player, CommandOriginType::DevConsole, CommandOriginType::Test] as $type) {
            foreach ([PHP_INT_MIN, -1, 0, 42, PHP_INT_MAX] as $playerId) {
                $packet = new CommandRequestPacket(
                    '/stop',
                    new CommandOrigin($type, self::NIL_UUID, '', $playerId),
                    true,
                );
                self::assertEquals($packet, CommandRequestPacket::decode($packet->encode()));
            }
        }
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

    public function testAvailableCommandsEncodesEnumsAliasesAndMultipleOverloads(): void
    {
        $packet = new AvailableCommandsPacket([
            new CommandDefinition('test', 'Test', CommandPermission::Any, [
                new CommandOverload([
                    new CommandParameter('mode', new CommandEnum('mode', ['give', 'clear'])),
                    new CommandParameter('player', new CommandEnum('bedriox:online_players', ['Alex', 'Steve'], true)),
                ]),
                new CommandOverload([new CommandParameter('target', CommandArgumentType::Target)]),
            ], aliases: ['t']),
        ]);
        $wire = hex2bin(
            '030174046769766505636c656172000002' .
            '1462656472696f783a616c69617365733a746573740100000000' .
            '046d6f64650201000000020000000001' .
            '04746573740454657374000003616e790000000000020002' .
            '046d6f646501003000000006706c61796572000010040000' .
            '00010674617267657408001000000001' .
            '1662656472696f783a6f6e6c696e655f706c61796572730204416c657805537465766500',
        );
        self::assertIsString($wire);
        self::assertSame($wire, $packet->encode());
        self::assertEquals($packet, AvailableCommandsPacket::decode($wire));
    }

    public function testAvailableCommandsAllowsPrimaryNameInOwnAliasEnum(): void
    {
        $packet = new AvailableCommandsPacket([
            new CommandDefinition('version', 'Show version', aliases: ['version', 'ver']),
            new CommandDefinition('list', 'List players'),
        ]);

        $decoded = AvailableCommandsPacket::decode($packet->encode());

        self::assertSame(['version', 'ver'], $decoded->commands[0]->aliases);
        self::assertSame([], $decoded->commands[1]->aliases);
    }

    public function testSoftEnumUpdatesMatchCurrentProtocolVectorAndRegistry(): void
    {
        $packet = new UpdateSoftEnumPacket(
            'bedriox:online_players',
            ['Alex', 'Steve'],
            SoftEnumUpdateType::Replace,
        );
        $wire = hex2bin('1662656472696f783a6f6e6c696e655f706c61796572730204416c657805537465766502');
        self::assertIsString($wire);
        self::assertSame($wire, $packet->encode());
        self::assertEquals($packet, UpdateSoftEnumPacket::decode($wire));
        self::assertEquals($packet, BedrockPacketCodec::decode(PacketIds::UPDATE_SOFT_ENUM, $wire));
        self::assertSame(PacketIds::UPDATE_SOFT_ENUM, BedrockPacketCodec::packetId($packet));

        foreach (SoftEnumUpdateType::cases() as $type) {
            $update = new UpdateSoftEnumPacket('players', [], $type);
            self::assertEquals($update, UpdateSoftEnumPacket::decode($update->encode()));
        }
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

    public function testCommandOutputMatchesCurrentProtocolVector(): void
    {
        $packet = new CommandOutputPacket(
            new CommandOrigin(CommandOriginType::Player, self::VECTOR_UUID, 'req-42', -2),
            CommandOutputType::AllOutput,
            1,
            [new CommandOutputMessage('commands.version', false, ['Bedriox', '1.0.0'])],
        );
        $wire = hex2bin(
            '06706c61796572' .
            '7766554433221100ffeeddccbbaa9988' .
            '067265712d3432' .
            'feffffffffffffff' .
            '09616c6c6f7574707574' .
            '01000000' .
            '01' .
            '10636f6d6d616e64732e76657273696f6e' .
            '00' .
            '02' .
            '0742656472696f78' .
            '05312e302e30' .
            '00',
        );
        self::assertIsString($wire);
        self::assertSame($wire, $packet->encode());
        self::assertEquals($packet, CommandOutputPacket::decode($wire));
        self::assertEquals($packet, BedrockPacketCodec::decode(PacketIds::COMMAND_OUTPUT, $wire));
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
        yield 'alias collides with a command' => [static function (): void {
            new AvailableCommandsPacket([
                new CommandDefinition('first', 'First', aliases: ['second']),
                new CommandDefinition('second', 'Second'),
            ]);
        }];
        yield 'hard enum is empty' => [static function (): void {
            new CommandEnum('mode', []);
        }];
        yield 'same enum name has conflicting values' => [static function (): void {
            new AvailableCommandsPacket([
                new CommandDefinition('test', 'Test', overloads: [new CommandOverload([
                    new CommandParameter('first', new CommandEnum('mode', ['one'])),
                    new CommandParameter('second', new CommandEnum('mode', ['two'])),
                ])]),
            ]);
        }];
    }

    #[DataProvider('invalidModelProvider')]
    public function testInvalidModelsAreRejected(callable $operation): void
    {
        $this->expectException(InvalidValueException::class);
        $operation();
    }

    public function testMalformedUtf8OriginIsRejected(): void
    {
        $originPrefix = "\x01\xff";
        $this->expectException(MalformedDataException::class);
        CommandRequestPacket::decode("\x00" . $originPrefix);
    }

    public function testCurrentCommandRequestRejectsTrailingData(): void
    {
        $packet = new CommandRequestPacket(
            '/version',
            new CommandOrigin(CommandOriginType::Player, self::NIL_UUID, 'id', 42),
        );
        $this->expectException(MalformedDataException::class);
        CommandRequestPacket::decode($packet->encode() . "\x00");
    }

    public function testUnknownCurrentOriginStringIsRejected(): void
    {
        $wire = hex2bin(
            '00' .
            '06626f67757321' .
            str_repeat('00', 16) .
            '00' .
            str_repeat('00', 8) .
            '00' .
            '066c6174657374',
        );
        self::assertIsString($wire);
        $this->expectException(MalformedDataException::class);
        CommandRequestPacket::decode($wire);
    }

    public function testObsoleteNumericOriginLayoutIsRejected(): void
    {
        $wire = hex2bin('082f76657273696f6e00' . str_repeat('00', 16) . '0361626300066c6174657374');
        self::assertIsString($wire);
        $this->expectException(MalformedDataException::class);
        CommandRequestPacket::decode($wire);
    }

    public function testOversizedOriginStringIsRejectedBeforeAllocation(): void
    {
        $this->expectException(MalformedDataException::class);
        CommandRequestPacket::decode("\x00\x81\x20");
    }

    public function testMalformedCommandEnumReferencesAreRejected(): void
    {
        $this->expectException(MalformedDataException::class);
        AvailableCommandsPacket::decode("\x00\x00\x00\x01\x01x\x01\x00\x00\x00\x00");
    }

    public function testDuplicateWireEnumNamesAreRejected(): void
    {
        $packet = new AvailableCommandsPacket([
            new CommandDefinition('test', 'Test', overloads: [new CommandOverload([
                new CommandParameter('first', new CommandEnum('mode', ['one'])),
                new CommandParameter('second', new CommandEnum('mood', ['two'])),
            ])]),
        ]);
        $wire = str_replace("\x04mood", "\x04mode", $packet->encode(), $replacements);
        self::assertSame(1, $replacements);
        $this->expectException(MalformedDataException::class);
        AvailableCommandsPacket::decode($wire);
    }

    public function testUnknownSoftEnumParameterReferenceIsRejected(): void
    {
        $packet = new AvailableCommandsPacket([
            new CommandDefinition('test', 'Test', overloads: [new CommandOverload([
                new CommandParameter('player', new CommandEnum('players', ['Alex'], true)),
            ])]),
        ]);
        $wire = str_replace(pack('V', 0x04100000), pack('V', 0x04100001), $packet->encode(), $replacements);
        self::assertSame(1, $replacements);
        $this->expectException(MalformedDataException::class);
        AvailableCommandsPacket::decode($wire);
    }

    public function testUnknownSoftEnumUpdateTypeIsRejected(): void
    {
        $wire = (new UpdateSoftEnumPacket('players', ['Alex'], SoftEnumUpdateType::Replace))->encode();
        $this->expectException(MalformedDataException::class);
        UpdateSoftEnumPacket::decode(substr($wire, 0, -1) . "\x03");
    }

    public function testOversizedCommandEnumValueCountIsRejectedBeforeIteration(): void
    {
        $this->expectException(MalformedDataException::class);
        AvailableCommandsPacket::decode("\x81\x20");
    }

    public function testTruncatedPacketsAreRejectedAtEveryBoundary(): void
    {
        $packets = [
            new CommandRequestPacket('/version', new CommandOrigin(CommandOriginType::Player, self::NIL_UUID, 'id')),
            new AvailableCommandsPacket([new CommandDefinition('version', 'Version', overloads: [new CommandOverload()])]),
            new CommandOutputPacket(new CommandOrigin(CommandOriginType::Player, self::NIL_UUID, 'id'), CommandOutputType::AllOutput, 0),
            new UpdateSoftEnumPacket('players', ['Alex', 'Steve'], SoftEnumUpdateType::Replace),
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
