<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Tests\Packet;

use Bedriox\Protocol\Exception\InvalidValueException;
use Bedriox\Protocol\Packet\ActorMetadata;
use Bedriox\Protocol\Packet\BoatActorMetadata;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class BoatActorMetadataTest extends TestCase
{
    public function testBaselineUsesCurrentBoatMetadataFieldsInAscendingOrder(): void
    {
        $metadata = BoatActorMetadata::baseline(10, 4.0, 9, -1, 0.08, 0.12);

        self::assertSame([0, 1, 2, 11, 12, 13, 14, 118, 119], array_map(
            static fn(ActorMetadata $entry): int => $entry->id,
            $metadata,
        ));
        self::assertSame(10, $metadata[2]->value);
        self::assertSame(9, $metadata[3]->value);
        self::assertSame(-1, $metadata[4]->value);
        self::assertSame(0.08, $metadata[5]->value);
        self::assertSame(0.12, $metadata[6]->value);
        self::assertSame(1, $metadata[7]->value);
        $buoyancyData = $metadata[8]->value;
        self::assertIsString($buoyancyData);
        self::assertSame([
            'apply_gravity' => true,
            'base_buoyancy' => 1.0,
            'big_wave_probability' => 0.03,
            'big_wave_speed' => 10.0,
            'can_auto_step_from_liquid' => false,
            'drag_down_on_buoyancy_removed' => 0.0,
            'liquid_blocks' => ['minecraft:water', 'minecraft:flowing_water'],
            'movement_type' => 'waves',
        ], json_decode($buoyancyData, true, flags: JSON_THROW_ON_ERROR));
    }

    /** @return iterable<string, array{int, float, int, int, float, float}> */
    public static function invalidValues(): iterable
    {
        yield 'negative variant' => [-1, 0.0, 0, 1, 0.0, 0.0];
        yield 'oversized variant' => [0x8000, 0.0, 0, 1, 0.0, 0.0];
        yield 'negative integrity' => [0, -1.0, 0, 1, 0.0, 0.0];
        yield 'negative hurt ticks' => [0, 0.0, -1, 1, 0.0, 0.0];
        yield 'invalid hurt direction' => [0, 0.0, 0, 0, 0.0, 0.0];
        yield 'negative left paddle time' => [0, 0.0, 0, 1, -0.1, 0.0];
        yield 'non-finite right paddle time' => [0, 0.0, 0, 1, 0.0, INF];
    }

    #[DataProvider('invalidValues')]
    public function testRejectsInvalidValues(
        int $variant,
        float $integrity,
        int $hurtTicks,
        int $hurtDirection,
        float $left,
        float $right,
    ): void {
        $this->expectException(InvalidValueException::class);
        BoatActorMetadata::baseline($variant, $integrity, $hurtTicks, $hurtDirection, $left, $right);
    }
}
