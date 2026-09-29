<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Tests\Packet;

use Bedriox\Protocol\Exception\InvalidValueException;
use Bedriox\Protocol\Packet\LevelEventPacket;
use Bedriox\Protocol\Packet\LevelEventBlockFace;
use Bedriox\Protocol\Packet\LevelEventParticleColor;
use Bedriox\Protocol\Packet\LevelEventParticleType;
use Bedriox\Protocol\Packet\LevelEventPosition;
use Bedriox\Protocol\Packet\LevelEventType;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class LevelEventParticlePacketTest extends TestCase
{
    private LevelEventPosition $position;

    protected function setUp(): void
    {
        $this->position = new LevelEventPosition(1.0, 2.0, 3.0);
    }

    public function testCurrentParticleIndexSurfaceIsCompleteAndUnique(): void
    {
        $values = array_map(static fn(LevelEventParticleType $type): int => $type->value, LevelEventParticleType::cases());

        self::assertCount(99, $values);
        self::assertCount(99, array_unique($values));
        self::assertSame(1, min($values));
        self::assertSame(100, max($values));
        self::assertNotContains(24, $values);
    }

    public function testTypedFactoriesOwnParticleMasksAndPayloadPacking(): void
    {
        self::assertSame(0x4001, LevelEventPacket::particle($this->position, LevelEventParticleType::Bubble)->eventId);
        self::assertSame(42, LevelEventPacket::scalarParticle(
            $this->position,
            LevelEventParticleType::Smoke,
            42,
        )->data);
        self::assertSame(-16_776_958, LevelEventPacket::coloredParticle(
            $this->position,
            LevelEventParticleType::MobSpell,
            new LevelEventParticleColor(0, 1, 2),
        )->data);
        self::assertSame(0x1234, LevelEventPacket::terrainParticle($this->position, 0x1234)->data);
        self::assertSame(LevelEventType::PunchBlockUp, LevelEventPacket::punchBlockFace(
            $this->position,
            0x1234,
            LevelEventBlockFace::Up,
        )->type());
        self::assertSame(0x12340056, LevelEventPacket::itemBreakParticle($this->position, 0x1234, 0x56)->data);
        self::assertSame(0x05020304, LevelEventPacket::dragonEggTeleportParticle(
            $this->position,
            -2,
            3,
            -4,
        )->data);
        self::assertSame(0x0302, LevelEventPacket::mobSpawnParticle($this->position, 2, 3)->data);
        self::assertSame(LevelEventType::PotionSplash, LevelEventPacket::potionSplashParticle(
            $this->position,
            new LevelEventParticleColor(1, 2, 3, 4),
        )->type());
        self::assertSame(
            LevelEventType::EndermanTeleport,
            LevelEventPacket::endermanTeleportParticle($this->position)->type(),
        );
    }

    #[DataProvider('invalidPayloads')]
    public function testTypedFactoriesRejectInvalidPayloads(\Closure $factory): void
    {
        $this->expectException(InvalidValueException::class);

        $factory($this->position);
    }

    /** @return iterable<string, array{\Closure(LevelEventPosition): object}> */
    public static function invalidPayloads(): iterable
    {
        yield 'color channel' => [static fn(): LevelEventParticleColor => new LevelEventParticleColor(256, 0, 0)];
        yield 'wrong scalar type' => [static fn(LevelEventPosition $position): LevelEventPacket => LevelEventPacket::scalarParticle(
            $position,
            LevelEventParticleType::Flame,
            1,
        )];
        yield 'wrong color type' => [static fn(LevelEventPosition $position): LevelEventPacket => LevelEventPacket::coloredParticle(
            $position,
            LevelEventParticleType::Flame,
            new LevelEventParticleColor(1, 2, 3),
        )];
        yield 'item ID' => [static fn(LevelEventPosition $position): LevelEventPacket => LevelEventPacket::itemBreakParticle(
            $position,
            65_536,
            0,
        )];
        yield 'dragon offset' => [static fn(LevelEventPosition $position): LevelEventPacket => LevelEventPacket::dragonEggTeleportParticle(
            $position,
            -256,
            0,
            0,
        )];
        yield 'mob dimensions' => [static fn(LevelEventPosition $position): LevelEventPacket => LevelEventPacket::mobSpawnParticle(
            $position,
            0,
            256,
        )];
    }
}
