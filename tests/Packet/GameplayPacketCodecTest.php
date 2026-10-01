<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Tests\Packet;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Bedriox\Protocol\Codec\ByteBufferReader;
use Bedriox\Protocol\Codec\SignedVarInt;
use Bedriox\Protocol\Exception\CodecException;
use Bedriox\Protocol\Exception\InvalidValueException;
use Bedriox\Protocol\Exception\MalformedDataException;
use Bedriox\Protocol\Identity\VerifiedClientData;
use Bedriox\Protocol\Packet\AbilityLayer;
use Bedriox\Protocol\Packet\AbilityValueType;
use Bedriox\Protocol\Packet\AddPlayerPacket;
use Bedriox\Protocol\Packet\ActorMetadata;
use Bedriox\Protocol\Packet\ActorMetadataVector3;
use Bedriox\Protocol\Packet\ActorFlag;
use Bedriox\Protocol\Packet\AnimatePacket;
use Bedriox\Protocol\Packet\AvailableActorIdentifiersPacket;
use Bedriox\Protocol\Packet\BiomeDefinitionListPacket;
use Bedriox\Protocol\Packet\BlockPosition;
use Bedriox\Protocol\Packet\ChatPacket;
use Bedriox\Protocol\Packet\ChunkPosition;
use Bedriox\Protocol\Packet\ChunkRadiusUpdatedPacket;
use Bedriox\Protocol\Packet\CommandPermissionLevel;
use Bedriox\Protocol\Packet\CreativeContentPacket;
use Bedriox\Protocol\Packet\CraftingDataPacket;
use Bedriox\Protocol\Packet\ContainerClosePacket;
use Bedriox\Protocol\Packet\ContainerType;
use Bedriox\Protocol\Packet\GameRulesChangedPacket;
use Bedriox\Protocol\Packet\GameRuleSet;
use Bedriox\Protocol\Packet\InventoryContentPacket;
use Bedriox\Protocol\Packet\LevelChunkPacket;
use Bedriox\Protocol\Packet\ItemRegistryPacket;
use Bedriox\Protocol\Packet\InteractPacket;
use Bedriox\Protocol\Packet\JigsawStructureDataPacket;
use Bedriox\Protocol\Packet\LittleEndianNbtToNetwork;
use Bedriox\Protocol\Packet\MovePlayerMode;
use Bedriox\Protocol\Packet\MovePlayerPacket;
use Bedriox\Protocol\Packet\MobEquipmentPacket;
use Bedriox\Protocol\Packet\NetworkChunkPublisherUpdatePacket;
use Bedriox\Protocol\Packet\NetworkStackLatencyPacket;
use Bedriox\Protocol\Packet\Packet;
use Bedriox\Protocol\Packet\PacketIds;
use Bedriox\Protocol\Packet\PlayerListRemovePacket;
use Bedriox\Protocol\Packet\PlayerListAddEntry;
use Bedriox\Protocol\Packet\PlayerListAddPacket;
use Bedriox\Protocol\Packet\PlayerPermission;
use Bedriox\Protocol\Packet\PlayerSkin;
use Bedriox\Protocol\Packet\PlayerSkinPacket;
use Bedriox\Protocol\Packet\PlayerAbilities;
use Bedriox\Protocol\Packet\PlayerActorMetadata;
use Bedriox\Protocol\Packet\PlayerAuthInputFlag;
use Bedriox\Protocol\Packet\PlayerAuthInputPacket;
use Bedriox\Protocol\Packet\PlayerActionPacket;
use Bedriox\Protocol\Packet\PlayerActionType;
use Bedriox\Protocol\Packet\PlayerPositionProjection;
use Bedriox\Protocol\Packet\RequestChunkRadiusPacket;
use Bedriox\Protocol\Packet\RequestAbilityPacket;
use Bedriox\Protocol\Packet\SetActorDataPacket;
use Bedriox\Protocol\Packet\SetCommandsEnabledPacket;
use Bedriox\Protocol\Packet\SetDifficultyPacket;
use Bedriox\Protocol\Packet\SetPlayerGameTypePacket;
use Bedriox\Protocol\Packet\SetSpawnPositionPacket;
use Bedriox\Protocol\Packet\SetTimePacket;
use Bedriox\Protocol\Packet\SetLocalPlayerAsInitializedPacket;
use Bedriox\Protocol\Packet\ServerboundLoadingScreenPacket;
use Bedriox\Protocol\Packet\StartGamePacket;
use Bedriox\Protocol\Packet\SubChunkPacket;
use Bedriox\Protocol\Packet\SubChunkRequestPacket;
use Bedriox\Protocol\Packet\TrimDataPacket;
use Bedriox\Protocol\Packet\UpdateAbilitiesPacket;
use Bedriox\Protocol\Packet\UpdateAdventureSettingsPacket;
use Bedriox\Protocol\Packet\UpdateAttributesPacket;
use Bedriox\Protocol\Packet\BedrockPacketCodec;
use Bedriox\Protocol\Packet\VoxelShapesPacket;
use Bedriox\Protocol\ProtocolVersion;
use Bedriox\Protocol\Value\UnsignedLong;
use Bedriox\Protocol\Value\BuildPlatform;

final class GameplayPacketCodecTest extends TestCase
{
    /** @return iterable<string, array{Packet, string}> */
    public static function vectors(): iterable
    {
        yield 'move normal' => [
            new MovePlayerPacket(
                UnsignedLong::fromInt(1), 1.0, 2.0, -3.0, 10.0, 20.0, 30.0,
                MovePlayerMode::NORMAL, true, UnsignedLong::fromInt(0), UnsignedLong::fromInt(5),
            ),
            '010000803f00000040000040c0000020410000a0410000f0410001000005',
        ];
        yield 'move respawn' => [
            new MovePlayerPacket(
                UnsignedLong::fromInt(1), 1.0, 2.0, -3.0, 10.0, 20.0, 30.0,
                MovePlayerMode::RESPAWN, true, UnsignedLong::fromInt(0), UnsignedLong::fromInt(5),
            ),
            '010000803f00000040000040c0000020410000a0410000f0410101000005',
        ];
        yield 'move teleport conditional fields' => [
            new MovePlayerPacket(
                UnsignedLong::fromInt(1), 1.0, 2.0, -3.0, 10.0, 20.0, 30.0,
                MovePlayerMode::TELEPORT, true, UnsignedLong::fromInt(0), UnsignedLong::fromInt(5),
                2, -3,
            ),
            '010000803f00000040000040c0000020410000a0410000f0410201000102000000fdffffff05',
        ];
        yield 'move head rotation' => [
            new MovePlayerPacket(
                UnsignedLong::fromInt(1), 1.0, 2.0, -3.0, 10.0, 20.0, 30.0,
                MovePlayerMode::HEAD_ROTATION, false, UnsignedLong::fromInt(0), UnsignedLong::fromInt(5),
            ),
            '010000803f00000040000040c0000020410000a0410000f0410300000005',
        ];
        yield 'add player empty gameplay state' => [
            new AddPlayerPacket(
                '00112233-4455-6677-8899-aabbccddeeff', 'P', UnsignedLong::fromInt(2), '',
                1.0, 2.0, 3.0, 0.0, 0.0, 0.0, 10.0, 20.0, 30.0, 0,
                new PlayerAbilities(1, PlayerPermission::Member, CommandPermissionLevel::Normal, [new AbilityLayer(0, 0, 0, 0.05000000074505806, 1.0, 0.10000000149011612)]),
            ),
            '7766554433221100ffeeddccbbaa9988015002000000803f0000004000004040000000000000000000000000000020410000a0410000f041000000000000000000000000010000000000000001000100000000000000000000cdcc4c3d0000803fcdcccc3d0000ffffffff',
        ];
        yield 'add player complete actor baseline' => [
            new AddPlayerPacket(
                '00112233-4455-6677-8899-aabbccddeeff', 'P', UnsignedLong::fromInt(2), '',
                1.0, 2.0, 3.0, 0.0, 0.0, 0.0, 10.0, 20.0, 30.0, 0,
                new PlayerAbilities(1, PlayerPermission::Member, CommandPermissionLevel::Normal, [new AbilityLayer(0, 0, 0, 0.05000000074505806, 1.0, 0.10000000149011612)]),
                metadata: PlayerActorMetadata::baseline('Player'),
            ),
            '7766554433221100ffeeddccbbaa9988015002000000803f0000004000004040000000000000000000000000000020410000a0410000f0410000000000000000000e0007078080c28080828003010202280300000004040406506c617965720701019001250707012603030000803f2a010190013503039a99193f3603036666e63f510000015c07070078030300000000820108089a99193f6666e63f9a99193f0000010000000000000001000100000000000000000000cdcc4c3d0000803fcdcccc3d0000ffffffff',
        ];
        yield 'chat' => [new ChatPacket('Name', 'hi', 'xid'), '000101044e616d65026869037869640000'];
        yield 'open inventory interaction' => [new InteractPacket(InteractPacket::OPEN_INVENTORY, UnsignedLong::fromInt(0)), '060000'];
        yield 'swing animation' => [
            new AnimatePacket(AnimatePacket::SWING, UnsignedLong::fromInt(7), 0.0, 'interact'),
            '0107000000000108696e746572616374',
        ];
        yield 'player action' => [
            new PlayerActionPacket(
                UnsignedLong::fromInt(7),
                PlayerActionType::StartFlying,
                new BlockPosition(1, 64, -2),
                new BlockPosition(-3, 65, 4),
                -1,
            ),
            '0744028001030582010801',
        ];
        yield 'container close' => [new ContainerClosePacket(255, ContainerType::Container, false), 'ff0000'];
        yield 'network stack latency' => [
            new NetworkStackLatencyPacket(new UnsignedLong(0x11223344, 0x55667788), true),
            '887766554433221101',
        ];
        yield 'request boolean ability' => [new RequestAbilityPacket(9, AbilityValueType::Bool, true, 1.0), '1201010000803f'];
        yield 'request vertical fly speed ability' => [new RequestAbilityPacket(19, AbilityValueType::Float, false, 1.0), '2602000000803f'];
        yield 'level chunk envelope' => [new LevelChunkPacket(0, 0, 0, 1, 'abc'), '0000000100000003616263'];
        yield 'player list remove' => [
            new PlayerListRemovePacket(['00112233-4455-6677-8899-aabbccddeeff']),
            '0100017766554433221100ffeeddccbbaa9988',
        ];
        yield 'request radius' => [new RequestChunkRadiusPacket(2, 8), '0408'];
        yield 'request retail-scale radius' => [new RequestChunkRadiusPacket(96, 255), 'c001ff'];
        yield 'radius updated' => [new ChunkRadiusUpdatedPacket(2), '04'];
        yield 'initialized' => [new SetLocalPlayerAsInitializedPacket(UnsignedLong::fromInt(300)), 'ac02'];
        yield 'initial loading screen' => [new ServerboundLoadingScreenPacket(ServerboundLoadingScreenPacket::START, null), '0200'];
        yield 'identified loading screen' => [new ServerboundLoadingScreenPacket(ServerboundLoadingScreenPacket::START, 7), '020107000000'];
        yield 'publisher update' => [
            new NetworkChunkPublisherUpdatePacket(1, 64, -2, 16, [new ChunkPosition(-1, 2)]),
            '0280010310010000000104',
        ];
        yield 'sub-chunk request' => [
            new SubChunkRequestPacket(0, 0, 4, 0, [
                ['x' => 0, 'y' => -1, 'z' => 0],
                ['x' => 1, 'y' => 0, 'z' => -1],
            ]),
            '000200ff000100ff000000000400000000000000',
        ];
        yield 'player auth input movement' => [
            new PlayerAuthInputPacket(
                1.0, 2.0, 3.0, 65.5, 4.0, 0.5, -0.5, 2.0, [10, 50, 64], 1, 0, 1,
                1.0, 2.0, UnsignedLong::fromInt(5), 0.125, 0.25, 0.5, 0.5, -0.5, 0.0, 1.0, 0.0, 0.25, -0.25,
            ),
            '0000803f000000400000404000008342000080400000003f000000bf0000004003146480010100010000803f00000040050000003e0000803e0000003f00000000000000003f000000bf000000000000803f000000000000803e000080be',
        ];
    }

    #[DataProvider('vectors')]
    public function testLiteralVectorsRoundTripAndRejectEveryTruncation(Packet $packet, string $hex): void
    {
        $wire = hex2bin($hex);
        self::assertIsString($wire);
        self::assertSame($wire, BedrockPacketCodec::encode($packet));
        self::assertEquals($packet, BedrockPacketCodec::decode($packet->packetId(), $wire));

        for ($length = 0; $length < strlen($wire); ++$length) {
            try {
                BedrockPacketCodec::decode($packet->packetId(), substr($wire, 0, $length));
                self::fail("Truncation at {$length} was accepted for packet {$packet->packetId()}.");
            } catch (CodecException) {
            }
        }

        try {
            BedrockPacketCodec::decode($packet->packetId(), $wire . "\0");
            self::fail('Trailing gameplay packet data was accepted.');
        } catch (CodecException) {
            self::addToAssertionCount(1);
        }
    }

    /** @return iterable<string, array{int, string}> */
    public static function malformed(): iterable
    {
        yield 'move non-finite' => [PacketIds::MOVE_PLAYER, "\1" . pack('g', INF) . str_repeat("\0", 25)];
        yield 'move mode' => [PacketIds::MOVE_PLAYER, "\1" . str_repeat("\0", 24) . "\4\0\0\0"];
        yield 'chat wrong variant' => [PacketIds::TEXT, "\0\0\1\0\0\0"];
        yield 'chat translated' => [PacketIds::TEXT, "\1\1\1\0\1x\0\0\0"];
        yield 'chunk cache unsupported' => [PacketIds::LEVEL_CHUNK, "\0\0\0\0\1"];
        yield 'player list unknown action' => [PacketIds::PLAYER_LIST, "\2\0"];
        yield 'request negative radius' => [PacketIds::REQUEST_CHUNK_RADIUS, "\1\1"];
        yield 'publisher negative count' => [PacketIds::NETWORK_CHUNK_PUBLISHER_UPDATE, "\0\0\0\0\xff\xff\xff\xff"];
        yield 'auth input non-finite pitch' => [PacketIds::PLAYER_AUTH_INPUT, pack('g', INF)];
        yield 'auth input noncanonical input data' => [PacketIds::PLAYER_AUTH_INPUT, str_repeat("\0", 32) . "\1\1\x80\x00"];
        yield 'auth input flag overflow' => [PacketIds::PLAYER_AUTH_INPUT, str_repeat("\0", 32) . "\1\1\x84\x01"];
        yield 'animation unknown action' => [PacketIds::ANIMATE, "\2"];
        yield 'player action count sentinel' => [PacketIds::PLAYER_ACTION, "\0\x4e" . str_repeat("\0", 7)];
        yield 'container close invalid boolean' => [PacketIds::CONTAINER_CLOSE, "\0\0\2"];
        yield 'latency invalid boolean' => [PacketIds::NETWORK_STACK_LATENCY, str_repeat("\0", 8) . "\2"];
        yield 'request ability above current range' => [PacketIds::REQUEST_ABILITY, "\x28\0\0" . pack('g', 0.0)];
        yield 'request ability unknown value type' => [PacketIds::REQUEST_ABILITY, "\0\3\0" . pack('g', 0.0)];
        yield 'request ability invalid boolean' => [PacketIds::REQUEST_ABILITY, "\0\1\2" . pack('g', 0.0)];
        yield 'request ability non-finite float' => [PacketIds::REQUEST_ABILITY, "\0\2\0" . pack('g', INF)];
        yield 'loading screen unknown type' => [PacketIds::SERVERBOUND_LOADING_SCREEN, "\x06\0\0\0\0"];
        yield 'interact unknown action above range' => [PacketIds::INTERACT, "\x07\0\0"];
        yield 'actor metadata mismatched type marker' => [PacketIds::SET_ACTOR_DATA, "\1\1\0\7\0\0\0\0"];
        yield 'actor metadata unsupported type' => [PacketIds::SET_ACTOR_DATA, "\1\1\0\5\5\0\0\0"];
        yield 'actor metadata count overflow' => [PacketIds::SET_ACTOR_DATA, "\1\x81\x01"];
        yield 'actor metadata unordered IDs' => [PacketIds::SET_ACTOR_DATA, "\1\2\1\0\0\0\0\0\0\0\0"];
        yield 'actor metadata nonempty properties' => [PacketIds::SET_ACTOR_DATA, "\1\0\1\0\0"];
        yield 'actor metadata vector non-finite' => [PacketIds::SET_ACTOR_DATA, "\1\1\x82\x01\x08\x08" . pack('g', INF) . str_repeat("\0", 8) . "\0\0\0"];
    }

    #[DataProvider('malformed')]
    public function testMalformedPayloadsAreRejected(int $packetId, string $wire): void
    {
        $this->expectException(CodecException::class);
        BedrockPacketCodec::decode($packetId, $wire);
    }

    public function testMovePlayerTeleportPresenceMustMatchMode(): void
    {
        $normal = hex2bin('010000803f00000040000040c0000020410000a0410000f0410001000005');
        $teleport = hex2bin('010000803f00000040000040c0000020410000a0410000f0410201000102000000fdffffff05');
        self::assertIsString($normal);
        self::assertIsString($teleport);

        foreach ([
            substr($normal, 0, -2) . "\1" . substr($normal, -1),
            substr($teleport, 0, 28) . "\0" . substr($teleport, 29),
            substr($normal, 0, -2) . "\2" . substr($normal, -1),
        ] as $wire) {
            try {
                MovePlayerPacket::decode($wire);
                self::fail('Inconsistent move-player teleport presence was accepted.');
            } catch (CodecException) {
                self::addToAssertionCount(1);
            }
        }

        foreach ([
            static fn (): MovePlayerPacket => new MovePlayerPacket(
                UnsignedLong::fromInt(1), 0.0, 0.0, 0.0, 0.0, 0.0, 0.0,
                MovePlayerMode::NORMAL, false, UnsignedLong::fromInt(0), UnsignedLong::fromInt(0), 1,
            ),
            static fn (): MovePlayerPacket => new MovePlayerPacket(
                UnsignedLong::fromInt(1), 0.0, 0.0, 0.0, 0.0, 0.0, 0.0,
                MovePlayerMode::RESPAWN, false, UnsignedLong::fromInt(0), UnsignedLong::fromInt(0), 0, 1,
            ),
            static fn (): MovePlayerPacket => new MovePlayerPacket(
                UnsignedLong::fromInt(1), 0.0, 0.0, 0.0, 0.0, 0.0, 0.0,
                MovePlayerMode::TELEPORT, false, UnsignedLong::fromInt(0), UnsignedLong::fromInt(0), 5,
            ),
        ] as $createInvalid) {
            try {
                $createInvalid();
                self::fail('Move-player teleport metadata outside teleport mode was accepted.');
            } catch (InvalidValueException) {
                self::addToAssertionCount(1);
            }
        }
    }

    public function testPlayerAuthInputProjectsMovementAndRejectableEmptyStackRequest(): void
    {
        $ordinary = (new PlayerAuthInputPacket(
            1.0, 2.0, 3.0, 65.621, 4.0, 0.5, -0.5, 2.0, [], 1, 0, 0,
            1.0, 2.0, UnsignedLong::fromInt(5), 0.1, 0.2, 0.3, 0.0, 0.0, 0.0, 1.0, 0.0, 0.0, 0.0,
        ))->encode();
        $stackBase = substr($ordinary, 0, 32) . "\1\x48" . substr($ordinary, 33);
        $wire = substr($stackBase, 0, 59) . "\1\x0a\0\0\xff\xff\xff\xff" . substr($stackBase, 60);
        $decoded = BedrockPacketCodec::decode(PacketIds::PLAYER_AUTH_INPUT, $wire);
        self::assertInstanceOf(PlayerAuthInputPacket::class, $decoded);
        self::assertTrue($decoded->ignoredOptionalPayload);
        self::assertSame(3.0, $decoded->wireX);
        self::assertEqualsWithDelta(64.0, $decoded->feetY(), 0.00001);
        self::assertSame(5, $decoded->tick->toSignedBits());
        self::assertSame(5, $decoded->itemStackRequestId);
        self::assertSame($wire, $decoded->encode());
    }

    public function testRoutineSpawnedClientPacketsExposeSafeTypedSemantics(): void
    {
        $action = new PlayerActionPacket(
            UnsignedLong::fromInt(9),
            PlayerActionType::StartJump,
            new BlockPosition(0, 0, 0),
            new BlockPosition(0, 0, 0),
            0,
        );
        self::assertSame(PacketIds::PLAYER_ACTION, $action->packetId());
        self::assertSame(PlayerActionType::StartJump, $action->action);

        $animation = new AnimatePacket(AnimatePacket::SWING, UnsignedLong::fromInt(9), 0.0, null);
        self::assertTrue($animation->isSwing());

        $close = new ContainerClosePacket(255, ContainerType::Container, false);
        self::assertSame(-1, $close->signedContainerId());

        $latency = new NetworkStackLatencyPacket(new UnsignedLong(0xffffffff, 0xffffffff), false);
        $response = $latency->serverResponse();
        self::assertTrue($response->fromServer);
        self::assertTrue($latency->creationTime->equals($response->creationTime));

        $ability = new RequestAbilityPacket(9, AbilityValueType::Bool, true, 0.0);
        self::assertSame(AbilityValueType::Bool, $ability->valueType);
        self::assertTrue($ability->boolValue);
    }

    public function testPlayerActionRejectsReservedLegacyWireValues(): void
    {
        foreach ([3, 4, 19, 20, 25, 36] as $reserved) {
            try {
                BedrockPacketCodec::decode(
                    PacketIds::PLAYER_ACTION,
                    "\0" . SignedVarInt::encode($reserved) . str_repeat("\0", 7),
                );
                self::fail("Reserved player-action value {$reserved} was accepted.");
            } catch (MalformedDataException) {
                self::addToAssertionCount(1);
            }
        }
    }

    public function testConstructorBoundsAreEnforcedBeforeEncoding(): void
    {
        foreach ([
            static fn (): ChatPacket => new ChatPacket('name', str_repeat('x', 513)),
            static fn (): RequestChunkRadiusPacket => new RequestChunkRadiusPacket(0x80000000, 255),
            static fn (): ServerboundLoadingScreenPacket => new ServerboundLoadingScreenPacket(3, 0),
            static fn (): InteractPacket => new InteractPacket(InteractPacket::OPEN_INVENTORY, UnsignedLong::fromInt(0), 0.0, null, 0.0),
            static fn (): InteractPacket => new InteractPacket(7, UnsignedLong::fromInt(0)),
            static fn (): ActorMetadata => ActorMetadata::short(7, 0x8000),
            static fn (): SetActorDataPacket => new SetActorDataPacket(UnsignedLong::fromInt(1), UnsignedLong::fromInt(0), [
                ActorMetadata::byte(7, 0), ActorMetadata::byte(7, 1),
            ]),
            static fn (): LevelChunkPacket => new LevelChunkPacket(0, 0, 0, 513, ''),
            static fn (): PlayerListRemovePacket => new PlayerListRemovePacket(array_fill(0, 257, '00000000-0000-0000-0000-000000000000')),
            static fn (): MovePlayerPacket => new MovePlayerPacket(
                UnsignedLong::fromInt(1), NAN, 0.0, 0.0, 0.0, 0.0, 0.0,
                MovePlayerMode::NORMAL, false, UnsignedLong::fromInt(0), UnsignedLong::fromInt(0),
            ),
        ] as $operation) {
            try {
                $operation();
                self::fail('Invalid gameplay packet constructor input was accepted.');
            } catch (InvalidValueException) {
                self::addToAssertionCount(1);
            }
        }
    }

    public function testFixedFlatStartGameKnownVectorAndRegistryRoundTrip(): void
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
        );
        self::assertSame(
            '0202000000003f00008242000000bf00000000000000000000000000000000000006706c61696e730002000000008001000100000001000000000000000000000000010108080000000300000011646174615f64726976656e5f6974656d7301197570636f6d696e675f63726561746f725f6665617475726573011c6578706572696d656e74616c5f6d6f6c616e675f66656174757265730101000001040000000000000000000000000007312e32362e353010000000100000000000000000000000056c6576656c04466c617400005001000000000000000000000001000a00000000000000000000000000000000000000000000000000000001010000000000',
            bin2hex($packet->encode()),
        );
        self::assertStringContainsString("1.26.50", $packet->encode());
        self::assertStringContainsString('1.26.51', StartGamePacket::fixedFlat(
            1,
            UnsignedLong::fromInt(2),
            0.5,
            65.0,
            -0.5,
            'level',
            'Flat',
            gameRules: new GameRuleSet([]),
            gameVersion: '1.26.51',
        )->encode());
        self::assertStringNotContainsString('Bedriox', $packet->encode());
        self::assertSame(PacketIds::START_GAME, BedrockPacketCodec::packetId($packet));

        $defaultRules = GameRuleSet::survivalDefaults()->encode();
        self::assertSame(727, strlen($defaultRules));
        self::assertSame('ba21a8681ddfe9fb4bc60282f97423486dcae532336c34ed6d13de910a4f4541', hash('sha256', $defaultRules));
        $defaultStart = StartGamePacket::fixedFlat(1, UnsignedLong::fromInt(2), 0.5, 65.0, -0.5, 'level', 'Flat');
        self::assertSame(984, strlen($defaultStart->encode()));
        self::assertTrue($defaultStart->blockNetworkIdsAreHashes);
        self::assertSame('2dc8ba6efcb2b6ed7ad0215a5ec418cf51c4b56c27e8ee4cd1bd343eccb3fc2b', hash('sha256', $defaultStart->encode()));
    }

    public function testFixedFlatStartGameEncodesBoundedRewindHistoryBeforeAuthoritativeBlockBreaking(): void
    {
        $default = StartGamePacket::fixedFlat(
            1,
            UnsignedLong::fromInt(2),
            0.5,
            65.0,
            -0.5,
            'level',
            'Flat',
            gameRules: new GameRuleSet([]),
        )->encode();
        $configured = StartGamePacket::fixedFlat(
            1,
            UnsignedLong::fromInt(2),
            0.5,
            65.0,
            -0.5,
            'level',
            'Flat',
            gameRules: new GameRuleSet([]),
            rewindHistorySize: 300,
        )->encode();

        // premium template ID, trial, rewind history, authoritative block breaking,
        // current tick, and enchantment seed in their protocol-2193 order.
        self::assertSame('00005001000000000000000000', bin2hex(substr($default, 206, 13)));
        self::assertSame('0000d80401000000000000000000', bin2hex(substr($configured, 206, 14)));
    }

    public function testFixedFlatStartGameBoundsRewindHistoryToItsNonNegativeWireDomain(): void
    {
        foreach ([0, 0x7fffffff] as $rewindHistorySize) {
            $packet = StartGamePacket::fixedFlat(
                1,
                UnsignedLong::fromInt(2),
                0.0,
                64.0,
                0.0,
                'level',
                'Flat',
                rewindHistorySize: $rewindHistorySize,
            );
            self::assertNotSame('', $packet->encode());
        }

        foreach ([-1, 0x80000000] as $rewindHistorySize) {
            try {
                StartGamePacket::fixedFlat(
                    1,
                    UnsignedLong::fromInt(2),
                    0.0,
                    64.0,
                    0.0,
                    'level',
                    'Flat',
                    rewindHistorySize: $rewindHistorySize,
                );
                self::fail('An out-of-range StartGame rewind-history size was accepted.');
            } catch (InvalidValueException) {
                self::addToAssertionCount(1);
            }
        }
    }

    public function testFixedFlatStartGameEncodesConfiguredSeedAndWorldSpawn(): void
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
            worldSeed: -2,
            worldSpawnX: -12,
            worldSpawnY: -34,
            worldSpawnZ: 56,
        );

        self::assertSame(
            '0202000000003f00008242000000bf0000000000000000feffffffffffffff000006706c61696e7300020000001743700100000001000000000000000000000000010108080000000300000011646174615f64726976656e5f6974656d7301197570636f6d696e675f63726561746f725f6665617475726573011c6578706572696d656e74616c5f6d6f6c616e675f66656174757265730101000001040000000000000000000000000007312e32362e353010000000100000000000000000000000056c6576656c04466c617400005001000000000000000000000001000a00000000000000000000000000000000000000000000000000000001010000000000',
            bin2hex($packet->encode()),
        );
    }

    public function testFixedFlatStartGameEncodesTheAuthoritativePlayerRotation(): void
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
            playerPitch: -30.0,
            playerYaw: 145.0,
        );

        self::assertStringStartsWith(
            '0202000000003f00008242000000bf0000f0c100001143',
            bin2hex($packet->encode()),
        );
    }

    public function testFixedFlatStartGameAcceptsSignedLimitsAndRejectsOutOfRangeWorldSpawn(): void
    {
        foreach ([PHP_INT_MIN, PHP_INT_MAX] as $seed) {
            $packet = StartGamePacket::fixedFlat(
                1,
                UnsignedLong::fromInt(2),
                0.0,
                64.0,
                0.0,
                'level',
                'Flat',
                worldSeed: $seed,
                worldSpawnX: -0x80000000,
                worldSpawnY: 0x7fffffff,
                worldSpawnZ: 0,
            );
            self::assertNotSame('', $packet->encode());
        }

        foreach (
            [
                ['worldSpawnX' => -0x80000001],
                ['worldSpawnY' => 0x80000000],
                ['worldSpawnZ' => -0x80000001],
            ] as $invalid
        ) {
            try {
                StartGamePacket::fixedFlat(...array_merge([
                    'uniqueEntityId' => 1,
                    'runtimeEntityId' => UnsignedLong::fromInt(2),
                    'x' => 0.0,
                    'y' => 64.0,
                    'z' => 0.0,
                    'levelId' => 'level',
                    'levelName' => 'Flat',
                ], $invalid));
                self::fail('An out-of-range StartGame world-spawn coordinate was accepted.');
            } catch (InvalidValueException) {
                self::addToAssertionCount(1);
            }
        }
    }

    public function testDataBackedBootstrapPacketShapes(): void
    {
        $jigsaw = new JigsawStructureDataPacket();
        self::assertSame(PacketIds::JIGSAW_STRUCTURE_DATA, BedrockPacketCodec::packetId($jigsaw));
        self::assertSame(
            '0a00090a70726f636573736f72730a00090e74656d706c6174655f706f6f6c730a0009076a6967736177730a00090e7374727563747572655f736574730a0000',
            bin2hex($jigsaw->encode()),
        );
        $voxelShapes = new VoxelShapesPacket();
        self::assertSame(PacketIds::VOXEL_SHAPES, BedrockPacketCodec::packetId($voxelShapes));
        self::assertSame('00000000', bin2hex($voxelShapes->encode()));
        $creative = new CreativeContentPacket();
        self::assertSame(PacketIds::CREATIVE_CONTENT, BedrockPacketCodec::packetId($creative));
        self::assertSame('0000', bin2hex($creative->encode()));

        // Bedriox/Data returns this typed map in artifact order; the public contract is keyed, not positional.
        $ids = ['dirt' => 10382, 'grass_block' => 11594, 'air' => 13080, 'bedrock' => 13791];
        $chunk = LevelChunkPacket::fixedFlat(0, 0, $ids, 1);
        self::assertSame(8, $chunk->subChunkCount);
        self::assertSame(2_141, strlen($chunk->data));
        self::assertSame('6add0a8c81317395c4141395a086b18dcd36bdb97b5a8a942834ff76819fea44', hash('sha256', $chunk->data));
        self::assertSame('0901fc01b0cc010901fd01b0cc010901fe01b0cc010901ff01b0cc0109010001b0cc0109010101b0cc0109010201b0cc0109010305', bin2hex(substr($chunk->data, 0, 53)));
        self::assertSame(str_repeat("\x00\x00\x00\xe9", 256), substr($chunk->data, 53, 1024));
        self::assertEquals($chunk, BedrockPacketCodec::decode(PacketIds::LEVEL_CHUNK, $chunk->encode()));

        $biomes = BiomeDefinitionListPacket::fromDefinitions([[
            'name' => 'minecraft:plains', 'id' => 1, 'temperature' => 0.8, 'downfall' => 0.4,
            'foliage_snow' => 0.0, 'depth' => 0.125, 'scale' => 0.05, 'map_water_argb' => 0xff3f76e4,
            'rain' => true, 'tags' => ['overworld'],
        ]]);
        self::assertSame('010000ffffcdcc4c3fcdcccc3e000000000000003ecdcc4c3de4763fff01010101000002106d696e6563726166743a706c61696e73096f766572776f726c64', bin2hex($biomes->encode()));
        self::assertSame(
            '0a00090669646c6973740a0108026964106d696e6563726166743a706c617965720000',
            bin2hex(AvailableActorIdentifiersPacket::fromIdentifiers(['minecraft:player'])->encode()),
        );
        self::assertSame('010d6d696e6563726166743a616972000000040a0d6d696e6563726166743a61697200', bin2hex(ItemRegistryPacket::fromRequiredItems([
            'minecraft:air' => ['runtime_id' => 0, 'component_based' => false, 'version' => 2],
        ])->encode()));
    }

    public function testFlatChunkShellAndRequestedTerrainAreBounded(): void
    {
        $ids = ['air' => 13_629, 'bedrock' => 14_348, 'dirt' => 10_854, 'grass_block' => 12_086];
        $shell = LevelChunkPacket::fixedFlatShell(0, 0, 1);
        self::assertSame(0, $shell->subChunkCount);
        self::assertTrue($shell->requestSubChunks);
        self::assertSame(8, $shell->subChunkLimit);
        self::assertSame("\x01\x02" . str_repeat("\xff", 23) . "\x00", $shell->data);
        self::assertEquals($shell, LevelChunkPacket::decode($shell->encode()));

        $request = new SubChunkRequestPacket(0, 0, 3, 0, [
            ['x' => 0, 'y' => 0, 'z' => 0],
            ['x' => 0, 'y' => -1, 'z' => 0],
            ['x' => 2, 'y' => 0, 'z' => 0],
        ]);
        $response = SubChunkPacket::fixedFlat($request, $ids, 1);
        self::assertSame(PacketIds::SUB_CHUNK, $response->packetId());
        self::assertSame(1, $response->responses[0]->result);
        self::assertSame(6, $response->responses[1]->result);
        self::assertSame(2, $response->responses[2]->result);
        self::assertSame(256, strlen($response->responses[0]->heightMap ?? ''));
        self::assertSame(1_041, strlen($response->responses[0]->data ?? ''));
        self::assertSame($response->encode(), BedrockPacketCodec::encode($response, 2193));
        self::assertSame('00', bin2hex(BedrockPacketCodec::encode($response, 2193)[0]));
    }

    public function testBedrockBootstrapStateVectors(): void
    {
        $vectors = [
            [new SetTimePacket(), PacketIds::SET_TIME, '00'],
            [new SetSpawnPositionPacket(), PacketIds::SET_SPAWN_POSITION, '00008001000000800100'],
            [new SetCommandsEnabledPacket(), PacketIds::SET_COMMANDS_ENABLED, '01'],
            [new SetDifficultyPacket(), PacketIds::SET_DIFFICULTY, '00'],
            [new SetPlayerGameTypePacket(), PacketIds::SET_PLAYER_GAME_TYPE, '00'],
            [new GameRulesChangedPacket(), PacketIds::GAME_RULES_CHANGED, '00'],
            [new CraftingDataPacket(), PacketIds::CRAFTING_DATA, str_repeat('00', 11) . '01'],
            [new TrimDataPacket(), PacketIds::TRIM_DATA, '0000'],
            [new UpdateAdventureSettingsPacket(), PacketIds::UPDATE_ADVENTURE_SETTINGS, '0000000001'],
            [new SetActorDataPacket(UnsignedLong::fromInt(7), UnsignedLong::fromInt(0)), PacketIds::SET_ACTOR_DATA, '0700000000'],
            [new MobEquipmentPacket(UnsignedLong::fromInt(7)), PacketIds::MOB_EQUIPMENT, '070000000000000000000000'],
        ];
        foreach ($vectors as [$packet, $id, $hex]) {
            self::assertSame($id, BedrockPacketCodec::packetId($packet));
            self::assertSame($hex, bin2hex($packet->encode()));
        }

        $inventory = new InventoryContentPacket(119, 1);
        self::assertSame(PacketIds::INVENTORY_CONTENT, BedrockPacketCodec::packetId($inventory));
        self::assertSame(
            '7701' . str_repeat('0000010000000000', 1) . '00000000010000000000',
            bin2hex($inventory->encode()),
        );

        $abilities = UpdateAbilitiesPacket::survival(7);
        self::assertSame(PacketIds::UPDATE_ABILITIES, BedrockPacketCodec::packetId($abilities));
        self::assertSame(
            '07000000000000000100010100ffff0f003f000000cdcc4c3d0000803fcdcccc3d',
            bin2hex($abilities->encode()),
        );
        self::assertSame(PlayerPermission::Member, $abilities->abilities->playerPermission);
        self::assertSame(CommandPermissionLevel::Normal, $abilities->abilities->commandPermission);
        $layer = $abilities->abilities->layers[0];
        self::assertSame(0x000fffff, $layer->abilitiesSet);
        self::assertSame(0x3f, $layer->abilityValues);
        foreach ([9, 10, 13, 14, 17, 19] as $clearBit) {
            self::assertSame(0, $layer->abilityValues & (1 << $clearBit));
        }
        self::assertSame(0.05, $layer->flySpeed);
        self::assertSame(1.0, $layer->verticalFlySpeed);
        self::assertSame(0.1, $layer->walkSpeed);
        $attributes = UpdateAttributesPacket::survival(UnsignedLong::fromInt(7));
        self::assertSame(PacketIds::UPDATE_ATTRIBUTES, BedrockPacketCodec::packetId($attributes));
        self::assertSame('07', bin2hex($attributes->encode()[0]));
        self::assertSame('05', bin2hex($attributes->encode()[1]));
        self::assertSame('00', bin2hex(substr($attributes->encode(), -1)));
    }

    public function testSurvivalOperatorAbilitiesUseExactPermissionAndCapabilityFields(): void
    {
        $abilities = UpdateAbilitiesPacket::survival(7, true);

        self::assertSame(
            '07000000000000000201010100ffff0f00ff000000cdcc4c3d0000803fcdcccc3d',
            bin2hex($abilities->encode()),
        );
        self::assertSame(PlayerPermission::Operator, $abilities->abilities->playerPermission);
        self::assertSame(CommandPermissionLevel::Operator, $abilities->abilities->commandPermission);
        self::assertSame(0x000fffff, $abilities->abilities->layers[0]->abilitiesSet);
        self::assertSame(0x000000ff, $abilities->abilities->layers[0]->abilityValues);
    }

    #[DataProvider('invalidAbilityPermissionVectors')]
    public function testUnknownAbilityPermissionLevelsAreRejected(string $wire): void
    {
        $this->expectException(MalformedDataException::class);
        PlayerAbilities::read(ByteBufferReader::fromString($wire, strlen($wire)));
    }

    /** @return iterable<string, array{string}> */
    public static function invalidAbilityPermissionVectors(): iterable
    {
        yield 'player permission' => [str_repeat("\0", 8) . "\x04\x00"];
        yield 'command permission' => [str_repeat("\0", 8) . "\x01\x06"];
    }

    public function testModernInteractActionsAndOptionalPositionAreExact(): void
    {
        foreach ([
            InteractPacket::INVALID,
            1,
            2,
            InteractPacket::VEHICLE_EXIT,
            InteractPacket::MOUSEOVER,
            InteractPacket::OPEN_NPC,
            InteractPacket::OPEN_INVENTORY,
        ] as $action) {
            $wire = chr($action) . "\x02\x00";
            $packet = BedrockPacketCodec::decode(PacketIds::INTERACT, $wire);
            self::assertInstanceOf(InteractPacket::class, $packet);
            self::assertSame($action, $packet->action);
            self::assertSame(2, $packet->targetRuntimeId->toSignedBits());
            self::assertSame($wire, $packet->encode());
        }

        $positioned = new InteractPacket(InteractPacket::MOUSEOVER, UnsignedLong::fromInt(2), 1.0, -2.0, 3.5);
        self::assertSame('0402010000803f000000c000006040', bin2hex($positioned->encode()));
        self::assertEquals($positioned, BedrockPacketCodec::decode(PacketIds::INTERACT, $positioned->encode()));
    }

    public function testBaselinePlayerActorMetadataKnownVectorAndTruncations(): void
    {
        self::assertSame(48, ActorFlag::HasCollision->value);
        self::assertSame(49, ActorFlag::HasGravity->value);
        self::assertSame(1, ActorFlag::Sneaking->value);
        self::assertSame(3, ActorFlag::Sprinting->value);
        self::assertSame(4, ActorFlag::UsingItem->value);
        self::assertSame(8, ActorFlag::Saddled->value);
        self::assertSame(11, ActorFlag::Baby->value);
        self::assertSame(16, ActorFlag::NoAi->value);
        self::assertSame(31, ActorFlag::Sheared->value);

        $packet = SetActorDataPacket::baselinePlayer(UnsignedLong::fromInt(7), UnsignedLong::fromInt(0), 'Player');
        $wire = hex2bin(
            '070e0007078080c28080828003010202280300000004040406506c61796572070101900125070701'
            . '2603030000803f2a010190013503039a99193f3603036666e63f510000015c07070078030300000000'
            . '820108089a99193f6666e63f9a99193f000000',
        );
        self::assertIsString($wire);
        self::assertSame($wire, $packet->encode());
        $decoded = BedrockPacketCodec::decode(PacketIds::SET_ACTOR_DATA, $wire);
        self::assertInstanceOf(SetActorDataPacket::class, $decoded);
        self::assertSame($wire, $decoded->encode());
        $expectedFlags = ActorFlag::combine(
            ActorFlag::CanShowName,
            ActorFlag::CanClimb,
            ActorFlag::Breathing,
            ActorFlag::HasCollision,
            ActorFlag::HasGravity,
        );
        self::assertSame($expectedFlags, $decoded->metadata[0]->value);
        foreach ([ActorFlag::CanShowName, ActorFlag::CanClimb, ActorFlag::Breathing, ActorFlag::HasCollision, ActorFlag::HasGravity] as $flag) {
            self::assertSame($flag->mask(), $expectedFlags & $flag->mask());
        }
        self::assertSame(0, $expectedFlags & ActorFlag::Sneaking->mask());
        self::assertSame(0, $expectedFlags & ActorFlag::Sprinting->mask());
        self::assertSame('Player', $decoded->metadata[3]->value);
        self::assertSame(400, $decoded->metadata[4]->value);
        self::assertSame(400, $decoded->metadata[7]->value);
        self::assertTrue(PlayerActorMetadata::isFlags($decoded->metadata[0]));
        self::assertTrue(PlayerActorMetadata::isAirSupply($decoded->metadata[4]));
        self::assertTrue(PlayerActorMetadata::isMaximumAirSupply($decoded->metadata[7]));
        self::assertSame(123, PlayerActorMetadata::airSupply(123)->value);
        self::assertSame(0, $decoded->metadata[11]->value);
        self::assertSame(0.0, $decoded->metadata[12]->value);
        self::assertInstanceOf(ActorMetadataVector3::class, $decoded->metadata[13]->value);
        self::assertEqualsWithDelta(0.6, $decoded->metadata[13]->value->x, 0.000001);
        self::assertEqualsWithDelta(1.8, $decoded->metadata[13]->value->y, 0.000001);
        self::assertEqualsWithDelta(0.6, $decoded->metadata[13]->value->z, 0.000001);

        $posture = SetActorDataPacket::playerPosture(
            UnsignedLong::fromInt(7),
            UnsignedLong::fromInt(9),
            true,
            true,
            true,
        );
        $postureFlags = $posture->metadata[0]->value;
        self::assertIsInt($postureFlags);
        self::assertSame(ActorFlag::Sneaking->mask(), $postureFlags & ActorFlag::Sneaking->mask());
        self::assertSame(ActorFlag::Sprinting->mask(), $postureFlags & ActorFlag::Sprinting->mask());
        self::assertSame(ActorFlag::UsingItem->mask(), $postureFlags & ActorFlag::UsingItem->mask());
        foreach ([ActorFlag::CanShowName, ActorFlag::CanClimb, ActorFlag::Breathing, ActorFlag::HasCollision, ActorFlag::HasGravity] as $flag) {
            self::assertSame($flag->mask(), $postureFlags & $flag->mask());
        }
        self::assertEquals($posture, SetActorDataPacket::decode($posture->encode()));

        for ($length = 0; $length < strlen($wire); ++$length) {
            try {
                BedrockPacketCodec::decode(PacketIds::SET_ACTOR_DATA, substr($wire, 0, $length));
                self::fail("SetActorData truncation at {$length} was accepted.");
            } catch (CodecException) {
            }
        }
    }

    public function testPlayerListAddRequiresClientValidClassicSkin(): void
    {
        $appearance = new VerifiedClientData(64, 32, str_repeat("\x01", 64 * 32 * 4), 0, 0, '', '{}', [],
            skinId: 's', skinResourcePatchJson: '{"geometry":{"default":"geometry.humanoid.custom"}}', profileHash: 'profile');
        $packet = new PlayerListAddPacket([new PlayerListAddEntry(
            '00112233-4455-6677-8899-aabbccddeeff', 1, 'P', '', '', BuildPlatform::Unknown, PlayerSkin::fromVerifiedClientData($appearance),
        )]);
        self::assertSame('ed32f5decab78d32edb8a35aa034fc029bf9000cdc8ef46a2c3f4781ac378e1f', hash('sha256', $packet->encode()));
        self::assertEquals($packet, BedrockPacketCodec::decode(PacketIds::PLAYER_LIST, $packet->encode()));
    }

    public function testPeerPlatformZeroIsRejectedInBothSpawnPackets(): void
    {
        $abilities = new PlayerAbilities(1, PlayerPermission::Member, CommandPermissionLevel::Normal, [new AbilityLayer(1, 0, 0, 0.05, 1.0, 0.1)]);
        $addPlayer = new AddPlayerPacket(
            '00112233-4455-6677-8899-aabbccddeeff', 'P', UnsignedLong::fromInt(2), '',
            0.0, 0.0, 0.0, 0.0, 0.0, 0.0, 0.0, 0.0, 0.0, 0, $abilities,
        );
        $invalidAddPlayer = substr($addPlayer->encode(), 0, -4) . "\0\0\0\0";
        try {
            AddPlayerPacket::decode($invalidAddPlayer);
            self::fail('Undefined AddPlayer platform zero was accepted.');
        } catch (MalformedDataException) {
            self::addToAssertionCount(1);
        }

        $appearance = new VerifiedClientData(64, 32, str_repeat("\x01", 64 * 32 * 4), 0, 0, '', '{}', [],
            skinId: 's', skinResourcePatchJson: '{"geometry":{"default":"geometry.humanoid.custom"}}');
        $list = new PlayerListAddPacket([new PlayerListAddEntry(
            '00112233-4455-6677-8899-aabbccddeeff', 1, 'P', '', '', BuildPlatform::Unknown,
            PlayerSkin::fromVerifiedClientData($appearance),
        )]);
        $wire = $list->encode();
        $platformOffset = 1 + 1 + 1 + 16 + 1 + 1 + 1 + 1 + 1;
        $invalidList = substr_replace($wire, "\0\0\0\0", $platformOffset, 4);
        $this->expectException(MalformedDataException::class);
        PlayerListAddPacket::decode($invalidList);
    }

    public function testPlayerSkinUpdateUsesTheCurrentBoundedLayout(): void
    {
        $appearance = new VerifiedClientData(64, 32, str_repeat("\x01", 64 * 32 * 4), 0, 0, '', '{}', [],
            skinId: 'skin', skinResourcePatchJson: '{"geometry":{"default":"geometry.humanoid.custom"}}', profileHash: 'profile');
        $packet = new PlayerSkinPacket(
            '00112233-4455-6677-8899-aabbccddeeff',
            PlayerSkin::fromVerifiedClientData($appearance),
            'new',
            'old',
        );
        $wire = $packet->encode();

        self::assertSame(PacketIds::PLAYER_SKIN, BedrockPacketCodec::packetId($packet));
        self::assertSame('7f87385b6788edf8430fd092dcb34728acc7f05bc66e03060b7ff4ab42fb2e02', hash('sha256', $wire));
        self::assertEquals($packet, BedrockPacketCodec::decode(PacketIds::PLAYER_SKIN, $wire));
        for ($length = 0; $length < strlen($wire); ++$length) {
            try {
                BedrockPacketCodec::decode(PacketIds::PLAYER_SKIN, substr($wire, 0, $length));
                self::fail("PlayerSkin truncation at {$length} was accepted.");
            } catch (CodecException) {
            }
        }
        try {
            BedrockPacketCodec::decode(PacketIds::PLAYER_SKIN, $wire . "\x00");
            self::fail('PlayerSkin trailing data was accepted.');
        } catch (MalformedDataException) {
            self::addToAssertionCount(1);
        }
    }

    public function testLittleEndianComponentNbtIsConvertedAndBounded(): void
    {
        self::assertSame('0a000301785400', bin2hex(LittleEndianNbtToNetwork::convert(hex2bin('0a0000030100782a00000000') ?: '')));
        self::assertSame('0a00040178ffffffffffffffffff0100', bin2hex(LittleEndianNbtToNetwork::convert(hex2bin('0a000004010078000000000000008000') ?: '')));
        foreach (["\x0a\x00", "\x09\x00\x00", "\x0a\x00\x00\x00\x00", hex2bin('0a00000901006c0d0000000000') ?: ''] as $malformed) {
            try { LittleEndianNbtToNetwork::convert($malformed); self::fail('Malformed little-endian NBT was accepted.'); }
            catch (InvalidValueException) { self::addToAssertionCount(1); }
        }
    }

    public function testPlayerPositionProjectionUsesOneCanonicalEyeOffset(): void
    {
        self::assertSame(65.621, PlayerPositionProjection::feetToWireY(64.0));
        self::assertEqualsWithDelta(64.0, PlayerPositionProjection::wireToFeetY(65.621), 0.000001);
        $wire = (new PlayerAuthInputPacket(
            1.0, 2.0, 3.0, 65.621, 4.0, 0.5, -0.5, 2.0, [50], 1, 0, 1,
            1.0, 2.0, UnsignedLong::fromInt(5), 0.1, 0.2, 0.3, 0.5, -0.5, 0.0, 1.0, 0.0, 0.25, -0.25,
        ))->encode();
        $decoded = BedrockPacketCodec::decode(PacketIds::PLAYER_AUTH_INPUT, $wire);
        self::assertInstanceOf(PlayerAuthInputPacket::class, $decoded);
        self::assertTrue($decoded->onGroundHint());
        self::assertEqualsWithDelta(64.0, $decoded->feetY(), 0.00001);
    }

    public function testPlayerAuthInputExposesTypedAuthoritativeMovementSignals(): void
    {
        $packet = new PlayerAuthInputPacket(
            1.0, 2.0, 3.0, 65.621, 4.0, 0.5, -0.5, 2.0,
            [6, 31, 49, 50, 59, 60, 61, 65], 1, 0, 1,
            1.0, 2.0, UnsignedLong::fromInt(0x102), 0.125, 0.42, -0.25,
            0.5, -0.5, 0.0, 1.0, 0.0, 0.25, -0.25,
        );
        $decoded = BedrockPacketCodec::decode(PacketIds::PLAYER_AUTH_INPUT, $packet->encode());
        self::assertInstanceOf(PlayerAuthInputPacket::class, $decoded);
        self::assertSame([
            PlayerAuthInputFlag::Jumping,
            PlayerAuthInputFlag::StartJumping,
            PlayerAuthInputFlag::HorizontalCollision,
            PlayerAuthInputFlag::VerticalCollision,
            PlayerAuthInputFlag::JumpReleasedRaw,
            PlayerAuthInputFlag::JumpPressedRaw,
            PlayerAuthInputFlag::JumpCurrentRaw,
            PlayerAuthInputFlag::InternalUpdate,
        ], $decoded->typedInputFlags());
        self::assertTrue($decoded->jumpHeld());
        self::assertTrue($decoded->jumpPressed());
        self::assertTrue($decoded->jumpReleased());
        self::assertTrue($decoded->horizontalCollisionHint());
        self::assertTrue($decoded->onGroundHint());
        $predictedVelocity = $decoded->predictedVelocity();
        self::assertEqualsWithDelta(0.125, $predictedVelocity['x'], 0.000001);
        self::assertEqualsWithDelta(0.42, $predictedVelocity['y'], 0.000001);
        self::assertEqualsWithDelta(-0.25, $predictedVelocity['z'], 0.000001);
        self::assertSame(0x102, $decoded->tick->toSignedBits());
    }

    public function testPlayerAuthInputConditionalPresenceIsBoundedAndCannotBeEncodedWithoutPayload(): void
    {
        $ordinary = (new PlayerAuthInputPacket(
            0.0, 0.0, 0.0, 65.621, 0.0, 0.0, 0.0, 0.0, [], 1, 0, 0,
            0.0, 0.0, UnsignedLong::fromInt(0), 0.0, 0.0, 0.0, 0.0, 0.0, 0.0, 0.0, 0.0, 0.0, 0.0,
        ))->encode();

        $decoded = BedrockPacketCodec::decode(PacketIds::PLAYER_AUTH_INPUT, $ordinary, 2193);
        self::assertInstanceOf(PlayerAuthInputPacket::class, $decoded);
        self::assertFalse($decoded->ignoredOptionalPayload);

        $stackBase = substr($ordinary, 0, 32) . "\1\x48" . substr($ordinary, 33);
        $withStackRequest = substr($stackBase, 0, 59) . "\1\x0e\0\0\xff\xff\xff\xff" . substr($stackBase, 60);
        $decoded = BedrockPacketCodec::decode(PacketIds::PLAYER_AUTH_INPUT, $withStackRequest, 2193);
        self::assertInstanceOf(PlayerAuthInputPacket::class, $decoded);
        self::assertTrue($decoded->ignoredOptionalPayload);
        self::assertSame(7, $decoded->itemStackRequestId);

        foreach ([
            substr($ordinary, 0, 57) . "\1\0" . substr($ordinary, 58),
            substr($ordinary, 0, 58) . "\1\x0e\1" . substr($ordinary, 59),
        ] as $unsupported) {
            try {
                BedrockPacketCodec::decode(PacketIds::PLAYER_AUTH_INPUT, $unsupported, 2193);
                self::fail('Unsupported conditional payload was accepted opaquely.');
            } catch (CodecException) {
                self::addToAssertionCount(1);
            }
        }

        foreach ([34, 35, 36, 45, 66] as $flag) {
            try {
                (new PlayerAuthInputPacket(
                    0.0, 0.0, 0.0, 65.621, 0.0, 0.0, 0.0, 0.0, [$flag], 1, 0, 0,
                    0.0, 0.0, UnsignedLong::fromInt(0), 0.0, 0.0, 0.0, 0.0, 0.0, 0.0, 0.0, 0.0, 0.0, 0.0,
                ))->encode();
                self::fail("Input flag {$flag} was encoded without its conditional payload.");
            } catch (InvalidValueException) {
                self::addToAssertionCount(1);
            }
        }

        foreach ([substr($ordinary, 0, 57) . "\2", substr($ordinary, 0, 57) . "\1"] as $truncated) {
            try {
                BedrockPacketCodec::decode(PacketIds::PLAYER_AUTH_INPUT, $truncated, 2193);
                self::fail('Malformed current conditional presence was accepted.');
            } catch (CodecException) {
                self::addToAssertionCount(1);
            }
        }
    }

    public function testPlayerAuthInputAcceptsAbsentAndRejectsDuplicateInputData(): void
    {
        $minimal = (new PlayerAuthInputPacket(
            0.0, 0.0, 0.0, 65.621, 0.0, 0.0, 0.0, 0.0, [], 1, 0, 0,
            0.0, 0.0, UnsignedLong::fromInt(0), 0.0, 0.0, 0.0, 0.0, 0.0, 0.0, 0.0, 0.0, 0.0, 0.0,
        ))->encode();
        foreach ([substr($minimal, 0, 32) . "\2\x14\x14" . substr($minimal, 33)] as $malformed) {
            try {
                BedrockPacketCodec::decode(PacketIds::PLAYER_AUTH_INPUT, $malformed);
                self::fail('Duplicate input data was accepted.');
            } catch (MalformedDataException) {
                self::addToAssertionCount(1);
            }
        }

        $this->expectException(InvalidValueException::class);
        BedrockPacketCodec::decode(PacketIds::PLAYER_AUTH_INPUT, $minimal, ProtocolVersion::CURRENT + 1);
    }

    public function testCommonItemStackActionsAreStructurallyConsumedForExplicitRejection(): void
    {
        $ordinary = (new PlayerAuthInputPacket(
            0.0, 0.0, 0.0, 65.621, 0.0, 0.0, 0.0, 0.0, [], 1, 0, 0,
            0.0, 0.0, UnsignedLong::fromInt(0), 0.0, 0.0, 0.0, 0.0, 0.0, 0.0, 0.0, 0.0, 0.0, 0.0,
        ))->encode();
        $ordinary = substr($ordinary, 0, 32) . "\1\x48" . substr($ordinary, 33);
        $slot = "\x1c\0\0" . pack('V', 0); // inventory, no dynamic ID, slot zero, stack network ID zero
        $actions = [
            "\0\0\1{$slot}{$slot}", // take
            "\1\1\1{$slot}{$slot}", // place
            "\2\2{$slot}{$slot}", // swap
            "\3\3\1{$slot}\0", // drop
            "\4\4\1{$slot}", // destroy
            "\5\5\1{$slot}", // consume
        ];
        foreach ($actions as $action) {
            $request = "\x0a\1{$action}\0\xff\xff\xff\xff";
            $wire = substr($ordinary, 0, 59) . "\1{$request}" . substr($ordinary, 60);
            $decoded = BedrockPacketCodec::decode(PacketIds::PLAYER_AUTH_INPUT, $wire);
            self::assertInstanceOf(PlayerAuthInputPacket::class, $decoded);
            self::assertSame(5, $decoded->itemStackRequestId);
            self::assertNotNull($decoded->itemStackRequest);
            self::assertCount(1, $decoded->itemStackRequest->actions);
        }

        foreach ([
            "\x0a\x65", // 101 actions exceeds the protocol bound
            "\x0a\1\0\0\1\x1c", // truncated take slot
            "\x0a\1\0\1", // duplicate action marker disagrees
            "\x0a\1\6\6", // unsupported crafting action
        ] as $request) {
            try {
                BedrockPacketCodec::decode(
                    PacketIds::PLAYER_AUTH_INPUT,
                    substr($ordinary, 0, 59) . "\1{$request}" . substr($ordinary, 60),
                );
                self::fail('Malformed or unsupported item-stack action was accepted.');
            } catch (CodecException) {
                self::addToAssertionCount(1);
            }
        }
    }
}
