<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Tests\Packet;

use Bedriox\Protocol\Exception\CodecException;
use Bedriox\Protocol\Exception\InvalidValueException;
use Bedriox\Protocol\Packet\BedrockPacketCodec;
use Bedriox\Protocol\Packet\AreaEffectCloudActorMetadata;
use Bedriox\Protocol\Packet\PotionProjectileActorMetadata;
use Bedriox\Protocol\Packet\TippedArrowActorMetadata;
use Bedriox\Protocol\Packet\DimensionId;
use Bedriox\Protocol\Packet\FishingHookActorMetadata;
use Bedriox\Protocol\Packet\LevelEventPosition;
use Bedriox\Protocol\Packet\MobEffectEvent;
use Bedriox\Protocol\Packet\MobEffectPacket;
use Bedriox\Protocol\Packet\MobEffectType;
use Bedriox\Protocol\Packet\PacketIds;
use Bedriox\Protocol\Packet\SpawnParticleEffectPacket;
use Bedriox\Protocol\Value\UnsignedLong;
use PHPUnit\Framework\TestCase;

final class EffectsAndParticlesPacketTest extends TestCase
{
    private const string MOB_EFFECT_VECTOR = 'ac0201020401b0091400';
    private const string PARTICLE_VECTOR = '02010000c03f000000c000008042017001027b7d';

    public function testIndependentLiteralMobEffectVectorAndRegistry(): void
    {
        $packet = MobEffectPacket::add(
            UnsignedLong::fromInt(300),
            MobEffectType::Speed,
            2,
            true,
            600,
            UnsignedLong::fromInt(20),
            false,
        );

        self::assertSame(self::MOB_EFFECT_VECTOR, bin2hex($packet->encode()));
        self::assertEquals($packet, MobEffectPacket::decode($packet->encode()));
        self::assertEquals($packet, BedrockPacketCodec::decode(PacketIds::MOB_EFFECT, $packet->encode()));
        self::assertSame(PacketIds::MOB_EFFECT, BedrockPacketCodec::packetId($packet));
        self::assertSame(28, $packet->packetId());
    }

    public function testIndependentLiteralParticleVectorAndRegistry(): void
    {
        $packet = new SpawnParticleEffectPacket(
            DimensionId::End,
            SpawnParticleEffectPacket::UNATTACHED_ENTITY_ID,
            new LevelEventPosition(1.5, -2.0, 64.0),
            'p',
            '{}',
        );

        self::assertSame(self::PARTICLE_VECTOR, bin2hex($packet->encode()));
        self::assertEquals($packet, SpawnParticleEffectPacket::decode($packet->encode()));
        self::assertEquals(
            $packet,
            BedrockPacketCodec::decode(PacketIds::SPAWN_PARTICLE_EFFECT, $packet->encode()),
        );
        self::assertSame(PacketIds::SPAWN_PARTICLE_EFFECT, BedrockPacketCodec::packetId($packet));
        self::assertSame(118, $packet->packetId());
    }

    public function testMobEffectFactoriesPreserveCurrentOperations(): void
    {
        $runtimeId = UnsignedLong::fromInt(7);
        $tick = UnsignedLong::fromInt(9);

        self::assertSame(
            MobEffectEvent::Modify,
            MobEffectPacket::modify($runtimeId, MobEffectType::Haste, 4, false, -1, $tick, true)->event,
        );
        $remove = MobEffectPacket::remove($runtimeId, MobEffectType::Haste, $tick);
        self::assertSame(MobEffectEvent::Remove, $remove->event);
        self::assertSame(0, $remove->amplifier);
        self::assertFalse($remove->particles);
        self::assertSame(0, $remove->duration);
        self::assertFalse($remove->ambient);

        $boundaries = new MobEffectPacket(
            new UnsignedLong(UnsignedLong::MAX_LIMB, UnsignedLong::MAX_LIMB),
            MobEffectEvent::None,
            MobEffectType::Speed,
            0x7fffffff,
            true,
            -1,
            new UnsignedLong(UnsignedLong::MAX_LIMB, UnsignedLong::MAX_LIMB),
            true,
        );
        self::assertEquals($boundaries, MobEffectPacket::decode($boundaries->encode()));
    }

    public function testParticleSupportsEachDimensionAndAbsentMolangVariables(): void
    {
        foreach (DimensionId::cases() as $dimension) {
            $packet = new SpawnParticleEffectPacket(
                $dimension,
                PHP_INT_MAX,
                new LevelEventPosition(-1.0, 0.0, 1.0),
                'minecraft:basic_flame_particle',
            );
            self::assertEquals($packet, SpawnParticleEffectPacket::decode($packet->encode()));
            self::assertNull(SpawnParticleEffectPacket::decode($packet->encode())->molangVariablesJson);
        }
    }

    public function testPotionActorMetadataUsesTypedBoundedFactories(): void
    {
        self::assertCount(2, PotionProjectileActorMetadata::baseline(22, true));
        self::assertCount(2, TippedArrowActorMetadata::baseline(22));
        self::assertCount(2, FishingHookActorMetadata::baseline(123));
        self::assertCount(10, AreaEffectCloudActorMetadata::baseline(3.0, 0x12345678));
        self::assertSame(-13083194, AreaEffectCloudActorMetadata::baseline(3.0, 0xff385dc6)[1]->value);

        $this->assertInvalidValue(static fn() => PotionProjectileActorMetadata::baseline(0x8000, false));
        $this->assertInvalidValue(static fn() => TippedArrowActorMetadata::baseline(0x100));
        $this->assertInvalidValue(static fn() => FishingHookActorMetadata::baseline(0));
        $this->assertInvalidValue(static fn() => AreaEffectCloudActorMetadata::baseline(INF));
    }

    public function testEveryMeaningfulTruncationAndTrailingByteFailsClosed(): void
    {
        foreach ([
            [self::MOB_EFFECT_VECTOR, MobEffectPacket::decode(...)],
            [self::PARTICLE_VECTOR, SpawnParticleEffectPacket::decode(...)],
        ] as [$hex, $decode]) {
            $wire = hex2bin($hex);
            self::assertIsString($wire);
            for ($length = 0; $length < strlen($wire); ++$length) {
                try {
                    $decode(substr($wire, 0, $length));
                    self::fail("Truncated effect packet was accepted at {$length} bytes.");
                } catch (CodecException) {
                    self::addToAssertionCount(1);
                }
            }
            try {
                $decode($wire . "\0");
                self::fail('Trailing effect packet byte was accepted.');
            } catch (CodecException) {
                self::addToAssertionCount(1);
            }
        }
    }

    public function testMalformedMobEffectValuesFailClosed(): void
    {
        foreach ([
            '00040000000000', // unknown operation
            '0001480000000000', // unknown current effect type 36
            '0000000002000000', // non-canonical true boolean
            '0000000000000002', // non-canonical ambient boolean
        ] as $hex) {
            $wire = hex2bin($hex);
            self::assertIsString($wire);
            try {
                MobEffectPacket::decode($wire);
                self::fail('Malformed mob-effect packet was accepted.');
            } catch (CodecException) {
                self::addToAssertionCount(1);
            }
        }

        foreach ([
            static fn () => new MobEffectPacket(
                UnsignedLong::fromInt(0),
                MobEffectEvent::Add,
                MobEffectType::Speed,
                0x80000000,
                false,
                0,
                UnsignedLong::fromInt(0),
                false,
            ),
            static fn () => new MobEffectPacket(
                UnsignedLong::fromInt(0),
                MobEffectEvent::Add,
                MobEffectType::Speed,
                -0x80000001,
                false,
                0,
                UnsignedLong::fromInt(0),
                false,
            ),
        ] as $createInvalid) {
            $this->assertInvalidValue($createInvalid);
        }
    }

    public function testMalformedParticleValuesFailClosed(): void
    {
        $invalidDimension = hex2bin(self::PARTICLE_VECTOR);
        self::assertIsString($invalidDimension);
        $invalidDimension[0] = "\x03";
        try {
            SpawnParticleEffectPacket::decode($invalidDimension);
            self::fail('Unknown particle dimension was accepted.');
        } catch (CodecException) {
            self::addToAssertionCount(1);
        }

        foreach ([
            "\0\1" . str_repeat("\0", 12) . "\0\2", // non-canonical optional marker
            "\0\1" . str_repeat("\0", 12) . "\1\xff\0", // invalid identifier UTF-8
            "\0\1" . str_repeat("\0", 12) . "\x81\x20" . str_repeat('x', 4_097) . "\0",
        ] as $wire) {
            try {
                SpawnParticleEffectPacket::decode($wire);
                self::fail('Malformed particle packet was accepted.');
            } catch (CodecException) {
                self::addToAssertionCount(1);
            }
        }

        $nonFinite = "\0\1" . pack('g', INF) . pack('g', 0.0) . pack('g', 0.0) . "\0\0";
        try {
            SpawnParticleEffectPacket::decode($nonFinite);
            self::fail('Non-finite particle position was accepted.');
        } catch (CodecException) {
            self::addToAssertionCount(1);
        }

        foreach ([
            static fn () => new SpawnParticleEffectPacket(
                DimensionId::Overworld,
                SpawnParticleEffectPacket::UNATTACHED_ENTITY_ID,
                new LevelEventPosition(0.0, 0.0, 0.0),
                str_repeat('x', SpawnParticleEffectPacket::MAX_IDENTIFIER_BYTES + 1),
            ),
            static fn () => new SpawnParticleEffectPacket(
                DimensionId::Overworld,
                SpawnParticleEffectPacket::UNATTACHED_ENTITY_ID,
                new LevelEventPosition(0.0, 0.0, 0.0),
                "\xff",
            ),
            static fn () => new SpawnParticleEffectPacket(
                DimensionId::Overworld,
                SpawnParticleEffectPacket::UNATTACHED_ENTITY_ID,
                new LevelEventPosition(0.0, 0.0, 0.0),
                'minecraft:test',
                str_repeat('x', SpawnParticleEffectPacket::MAX_MOLANG_VARIABLES_BYTES + 1),
            ),
        ] as $createInvalid) {
            $this->assertInvalidValue($createInvalid);
        }
    }

    /** @param callable(): mixed $createInvalid */
    private function assertInvalidValue(callable $createInvalid): void
    {
        try {
            $createInvalid();
            self::fail('Invalid packet value was constructed.');
        } catch (InvalidValueException) {
            self::addToAssertionCount(1);
        }
    }
}
