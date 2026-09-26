<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Tests\Packet;

use Bedriox\Protocol\Batch\BatchLimits;
use Bedriox\Protocol\Batch\BedrockBatch;
use Bedriox\Protocol\Batch\BedrockBatchCodec;
use Bedriox\Protocol\Batch\CompressionMode;
use Bedriox\Protocol\Codec\ByteBufferReader;
use Bedriox\Protocol\Codec\ByteBufferWriter;
use Bedriox\Protocol\Encryption\BedrockDecryptor;
use Bedriox\Protocol\Encryption\BedrockEncryptedEnvelopeCodec;
use Bedriox\Protocol\Encryption\BedrockEncryptor;
use Bedriox\Protocol\Exception\CodecException;
use Bedriox\Protocol\Exception\InvalidValueException;
use Bedriox\Protocol\Packet\ActorEventPacket;
use Bedriox\Protocol\Packet\ActorEventType;
use Bedriox\Protocol\Packet\ActorAttribute;
use Bedriox\Protocol\Packet\ActorAttributeModifier;
use Bedriox\Protocol\Packet\ActorAttributeOperation;
use Bedriox\Protocol\Packet\ActorFloatProperty;
use Bedriox\Protocol\Packet\ActorFlag;
use Bedriox\Protocol\Packet\ActorIntProperty;
use Bedriox\Protocol\Packet\ActorLink;
use Bedriox\Protocol\Packet\ActorLinkType;
use Bedriox\Protocol\Packet\ActorMetadata;
use Bedriox\Protocol\Packet\ActorProperties;
use Bedriox\Protocol\Packet\ActorSpawnAttribute;
use Bedriox\Protocol\Packet\AddActorPacket;
use Bedriox\Protocol\Packet\BedrockPacketCodec;
use Bedriox\Protocol\Packet\MobArmorEquipmentPacket;
use Bedriox\Protocol\Packet\MobEquipmentPacket;
use Bedriox\Protocol\Packet\Packet;
use Bedriox\Protocol\Packet\PacketFrame;
use Bedriox\Protocol\Packet\PacketHeader;
use Bedriox\Protocol\Packet\PacketIds;
use Bedriox\Protocol\Packet\RemoveActorPacket;
use Bedriox\Protocol\Packet\SetActorDataPacket;
use Bedriox\Protocol\Packet\SetActorLinkPacket;
use Bedriox\Protocol\Packet\SetActorMotionPacket;
use Bedriox\Protocol\Packet\UpdateAttributesPacket;
use Bedriox\Protocol\Value\UnsignedLong;
use PHPUnit\Framework\TestCase;

final class ActorPacketTest extends TestCase
{
    private const string KEY_HEX = '000102030405060708090a0b0c0d0e0f101112131415161718191a1b1c1d1e1f';

    public function testAddActorCurrentLayoutHasKnownVectorAndRegistryRoundTrip(): void
    {
        $packet = self::completeActor();
        self::assertSame(
            '03070d6d696e6563726166743a636f77'
            . '0000803f0000004000004040000080bf000000c0000040c0000020410000a0410000f04100002042'
            . '01106d696e6563726166743a6865616c746800000000000070410000a041'
            . '0104040403436f77'
            . '01020501030000c03f'
            . '0103060101000000803e',
            bin2hex($packet->encode()),
        );
        self::assertSame(PacketIds::ADD_ACTOR, BedrockPacketCodec::packetId($packet));
        self::assertEquals($packet, BedrockPacketCodec::decode(PacketIds::ADD_ACTOR, $packet->encode()));
        $this->assertRejectsEveryTruncation(AddActorPacket::decode(...), $packet->encode());
    }

    public function testActorPropertiesCanBeUpdatedAfterSpawn(): void
    {
        $packet = new SetActorDataPacket(
            UnsignedLong::fromInt(7),
            UnsignedLong::fromInt(300),
            [],
            new ActorProperties(
                [new ActorIntProperty(2, -3)],
                [new ActorFloatProperty(3, 1.5)],
            ),
        );
        self::assertSame('070001020501030000c03fac02', bin2hex($packet->encode()));
        self::assertEquals($packet, BedrockPacketCodec::decode(PacketIds::SET_ACTOR_DATA, $packet->encode()));
        $this->assertRejectsEveryTruncation(SetActorDataPacket::decode(...), $packet->encode());
    }

    public function testOnFireActorFlagUsesTheCurrentMetadataBit(): void
    {
        $flags = ActorFlag::combine(ActorFlag::OnFire, ActorFlag::HasCollision, ActorFlag::HasGravity);
        self::assertSame(1, ActorFlag::OnFire->mask());
        self::assertSame(1, $flags & ActorFlag::OnFire->mask());

        $packet = new SetActorDataPacket(
            UnsignedLong::fromInt(7),
            UnsignedLong::fromInt(300),
            [ActorMetadata::long(0, $flags)],
        );
        $decoded = BedrockPacketCodec::decode(PacketIds::SET_ACTOR_DATA, $packet->encode());

        self::assertInstanceOf(SetActorDataPacket::class, $decoded);
        self::assertSame($flags, $decoded->metadata[0]->value);
        self::assertSame($packet->encode(), $decoded->encode());
    }

    public function testGenericActorAttributesCanBeUpdatedAfterSpawn(): void
    {
        $packet = new UpdateAttributesPacket(
            UnsignedLong::fromInt(7),
            [new ActorAttribute(
                ActorAttribute::HEALTH,
                0.0,
                20.0,
                9.0,
                0.0,
                20.0,
                20.0,
                [new ActorAttributeModifier(
                    'bedriox:test',
                    'test',
                    1.5,
                    ActorAttributeOperation::Addition,
                    0,
                    true,
                )],
            )],
            UnsignedLong::fromInt(300),
        );
        self::assertSame(PacketIds::UPDATE_ATTRIBUTES, BedrockPacketCodec::packetId($packet));
        self::assertEquals($packet, BedrockPacketCodec::decode(PacketIds::UPDATE_ATTRIBUTES, $packet->encode()));
    }

    public function testActorLinksHaveCurrentVehicleFieldsAndTypedKinds(): void
    {
        $packet = new SetActorLinkPacket(new ActorLink(-2, 3, ActorLinkType::Rider, true, false, 0.25));
        self::assertSame('03060101000000803e', bin2hex($packet->encode()));
        self::assertEquals($packet, BedrockPacketCodec::decode(PacketIds::SET_ACTOR_LINK, $packet->encode()));
        $this->assertRejectsEveryTruncation(SetActorLinkPacket::decode(...), $packet->encode());

        $this->expectException(CodecException::class);
        SetActorLinkPacket::decode("\x03\x06\xff\0\0\0\0\0\0");
    }

    public function testCurrentActorEventMapIncludesMobAndAiLifecycleValues(): void
    {
        foreach ([
            [ActorEventType::Jump, 1],
            [ActorEventType::AttackStart, 4],
            [ActorEventType::TameSucceeded, 7],
            [ActorEventType::LoveParticles, 21],
            [ActorEventType::EntityGrowUp, 76],
            [ActorEventType::HurtWithoutReceivingDamage, 81],
        ] as [$event, $wireId]) {
            $packet = new ActorEventPacket(UnsignedLong::fromInt(7), $event);
            self::assertSame($wireId, ord($packet->encode()[1]));
            self::assertEquals($packet, ActorEventPacket::decode($packet->encode()));
        }
    }

    public function testActorConversationSurvivesBatchCompressionAndEncryption(): void
    {
        $runtimeId = UnsignedLong::fromInt(7);
        $packets = [
            self::completeActor(),
            new SetActorMotionPacket($runtimeId, 0.25, 0.5, -0.75, UnsignedLong::fromInt(1)),
            new MobEquipmentPacket($runtimeId),
            new MobArmorEquipmentPacket($runtimeId),
            new ActorEventPacket($runtimeId, ActorEventType::Hurt),
            new SetActorLinkPacket(new ActorLink(-2, 3, ActorLinkType::Rider)),
            new RemoveActorPacket(-2),
        ];
        $frames = array_map(
            static fn (Packet $packet): PacketFrame => new PacketFrame(
                new PacketHeader($packet->packetId()),
                BedrockPacketCodec::encode($packet),
            ),
            $packets,
        );
        $limits = new BatchLimits();
        $clear = BedrockBatchCodec::encode(new BedrockBatch($frames, CompressionMode::NegotiatedZlib), $limits);
        $key = hex2bin(self::KEY_HEX);
        self::assertIsString($key);
        $wire = BedrockEncryptedEnvelopeCodec::encode($clear, new BedrockEncryptor($key));
        $decodedClear = BedrockEncryptedEnvelopeCodec::decode($wire, new BedrockDecryptor($key));
        $decoded = BedrockBatchCodec::decode($decodedClear, CompressionMode::NegotiatedZlib, $limits);

        self::assertCount(count($packets), $decoded->packets);
        foreach ($packets as $index => $expected) {
            $frame = $decoded->packets[$index];
            self::assertSame($expected->packetId(), $frame->header->packetId);
            self::assertEquals($expected, BedrockPacketCodec::decode($frame->header->packetId, $frame->payload));
        }
    }

    public function testActorValuesRejectInvalidBoundsAndCollections(): void
    {
        $attribute = new ActorSpawnAttribute('minecraft:health', 0.0, 20.0, 20.0);
        $link = new ActorLink(1, 2, ActorLinkType::Rider);
        $modifier = new ActorAttributeModifier(
            'bedriox:test',
            'test',
            1.0,
            ActorAttributeOperation::Addition,
            0,
            true,
        );
        foreach ([
            static fn () => new ActorSpawnAttribute('', 0.0, 20.0, 20.0),
            static fn () => new ActorSpawnAttribute('minecraft:health', 20.0, 0.0, 10.0),
            static fn () => new ActorFloatProperty(0, NAN),
            static fn () => new ActorProperties([new ActorIntProperty(1, 1), new ActorIntProperty(1, 2)]),
            static fn () => new ActorLink(1, 2, ActorLinkType::Rider, vehicleAngularVelocity: INF),
            static fn () => new ActorAttribute(
                'minecraft:health',
                0.0, 20.0, 20.0,
                0.0, 20.0, 20.0,
                array_fill(0, 65, $modifier),
            ),
            static fn () => new UpdateAttributesPacket(
                UnsignedLong::fromInt(1),
                array_fill(0, 33, ActorAttribute::health(20.0)),
                UnsignedLong::fromInt(0),
            ),
            static fn () => new AddActorPacket(
                1,
                UnsignedLong::fromInt(1),
                'minecraft:cow',
                0.0, 0.0, 0.0,
                0.0, 0.0, 0.0,
                0.0, 0.0, 0.0, 0.0,
                array_fill(0, 65, $attribute),
            ),
            static fn () => new AddActorPacket(
                1,
                UnsignedLong::fromInt(1),
                'minecraft:cow',
                0.0, 0.0, 0.0,
                0.0, 0.0, 0.0,
                0.0, 0.0, 0.0, 0.0,
                links: array_fill(0, 65, $link),
            ),
            static fn () => new AddActorPacket(
                1,
                UnsignedLong::fromInt(1),
                '',
                0.0, 0.0, 0.0,
                0.0, 0.0, 0.0,
                0.0, 0.0, 0.0, 0.0,
            ),
        ] as $construct) {
            try {
                $construct();
                self::fail('Invalid actor value was accepted.');
            } catch (InvalidValueException) {
                $this->addToAssertionCount(1);
            }
        }

        $this->expectException(CodecException::class);
        ActorProperties::read(ByteBufferReader::fromString("\x81\x01", 2));
    }

    public function testAddActorRejectsOversizedWireAttributeCountBeforeIteration(): void
    {
        $writer = ByteBufferWriter::withCapacity(256)
            ->writeSignedVarLong(1)
            ->writeUnsignedVarLong(UnsignedLong::fromInt(1))
            ->writeString('minecraft:cow', 128);
        for ($index = 0; $index < 10; ++$index) {
            $writer = $writer->writeFloatLE(0.0);
        }

        $this->expectException(CodecException::class);
        AddActorPacket::decode($writer->writeUnsignedVarInt(65)->toString());
    }

    private static function completeActor(): AddActorPacket
    {
        return new AddActorPacket(
            -2,
            UnsignedLong::fromInt(7),
            'minecraft:cow',
            1.0, 2.0, 3.0,
            -1.0, -2.0, -3.0,
            10.0, 20.0, 30.0, 40.0,
            [new ActorSpawnAttribute('minecraft:health', 0.0, 20.0, 15.0)],
            [ActorMetadata::string(4, 'Cow')],
            new ActorProperties(
                [new ActorIntProperty(2, -3)],
                [new ActorFloatProperty(3, 1.5)],
            ),
            [new ActorLink(-2, 3, ActorLinkType::Rider, true, false, 0.25)],
        );
    }

    /** @param callable(string): Packet $decode */
    private function assertRejectsEveryTruncation(callable $decode, string $wire): void
    {
        for ($length = 0; $length < strlen($wire); ++$length) {
            try {
                $decode(substr($wire, 0, $length));
                self::fail("Truncated packet was accepted at {$length} bytes.");
            } catch (CodecException) {
                $this->addToAssertionCount(1);
            }
        }
        try {
            $decode($wire . "\0");
            self::fail('Packet accepted trailing data.');
        } catch (CodecException) {
            $this->addToAssertionCount(1);
        }
    }
}
