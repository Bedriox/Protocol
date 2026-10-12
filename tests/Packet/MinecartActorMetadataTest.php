<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Tests\Packet;

use Bedriox\Protocol\Exception\InvalidValueException;
use Bedriox\Protocol\Packet\ActorMetadata;
use Bedriox\Protocol\Packet\MinecartActorMetadata;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class MinecartActorMetadataTest extends TestCase
{
    public function testOrdinaryMinecartBaselineUsesCanonicalFields(): void
    {
        $metadata = MinecartActorMetadata::baseline(12.0, 7, -1);

        self::assertSame([0, 1, 11, 12, 17, 18], array_map(static fn(ActorMetadata $entry): int => $entry->id, $metadata));
        self::assertSame(12, $metadata[1]->value);
        self::assertSame(7, $metadata[2]->value);
        self::assertSame(-1, $metadata[3]->value);
        self::assertSame(6, $metadata[4]->value);
        self::assertSame(0, $metadata[5]->value);
        self::assertSame(ActorMetadata::TYPE_BYTE, $metadata[5]->type);
    }

    public function testChestMinecartCarriesSignedDisplayBlockRuntimeId(): void
    {
        $metadata = MinecartActorMetadata::baseline(displayBlockRuntimeId: -1_132_117_234);

        self::assertSame([0, 1, 11, 12, 16, 17, 18], array_map(static fn(ActorMetadata $entry): int => $entry->id, $metadata));
        self::assertSame(-1_132_117_234, $metadata[4]->value);
        self::assertSame(ActorMetadata::TYPE_INT, $metadata[4]->type);
        self::assertSame(1, $metadata[6]->value);
    }

    /** @return iterable<string, array{float, int, int}> */
    public static function invalidPresentationProvider(): iterable
    {
        yield 'negative damage' => [-1.0, 0, 1];
        yield 'non-finite damage' => [INF, 0, 1];
        yield 'negative hurt ticks' => [0.0, -1, 1];
        yield 'invalid hurt direction' => [0.0, 0, 0];
    }

    #[DataProvider('invalidPresentationProvider')]
    public function testInvalidPresentationIsRejected(float $damage, int $hurtTicks, int $hurtDirection): void
    {
        $this->expectException(InvalidValueException::class);

        MinecartActorMetadata::baseline($damage, $hurtTicks, $hurtDirection);
    }
}
