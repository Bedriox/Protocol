<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Tests\Packet;

use Bedriox\Protocol\Exception\InvalidValueException;
use Bedriox\Protocol\Packet\AddActorPacket;
use Bedriox\Protocol\Packet\ActorFlag;
use Bedriox\Protocol\Packet\ActorMetadata;
use Bedriox\Protocol\Packet\InteractionActorMetadata;
use Bedriox\Protocol\Packet\SetActorDataPacket;
use Bedriox\Protocol\Packet\TextDisplayActorMetadata;
use Bedriox\Protocol\Value\UnsignedLong;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class DisplayActorMetadataTest extends TestCase
{
    public function testTextDisplayBaselineUsesAirAndZeroBoundsInCanonicalOrder(): void
    {
        $metadata = TextDisplayActorMetadata::baseline("Welcome\nUptime: 1 minute", -604749536);

        self::assertSame([0, 2, 4, 38, 53, 54, 81], self::ids($metadata));
        self::assertSame([
            ActorMetadata::TYPE_LONG,
            ActorMetadata::TYPE_INT,
            ActorMetadata::TYPE_STRING,
            ActorMetadata::TYPE_FLOAT,
            ActorMetadata::TYPE_FLOAT,
            ActorMetadata::TYPE_FLOAT,
            ActorMetadata::TYPE_BYTE,
        ], self::types($metadata));
        self::assertSame(
            ActorFlag::combine(ActorFlag::CanShowName, ActorFlag::NoAi),
            $metadata[0]->value,
        );
        self::assertSame(-604749536, $metadata[1]->value);
        self::assertSame("Welcome\nUptime: 1 minute", $metadata[2]->value);
        self::assertEqualsWithDelta(0.01, $metadata[3]->value, 0.000001);
        self::assertSame(0.0, $metadata[4]->value);
        self::assertSame(0.0, $metadata[5]->value);
        self::assertSame(1, $metadata[6]->value);
        self::assertSame(4, TextDisplayActorMetadata::text('Updated')->id);
        self::assertSame(ActorMetadata::TYPE_STRING, TextDisplayActorMetadata::text('Updated')->type);
    }

    public function testInteractionBaselineIsInvisibleAndUsesDefaultBounds(): void
    {
        $metadata = InteractionActorMetadata::baseline();

        self::assertSame([0, 53, 54], self::ids($metadata));
        self::assertSame([
            ActorMetadata::TYPE_LONG,
            ActorMetadata::TYPE_FLOAT,
            ActorMetadata::TYPE_FLOAT,
        ], self::types($metadata));
        self::assertSame(
            ActorFlag::combine(ActorFlag::Invisible, ActorFlag::NoAi),
            $metadata[0]->value,
        );
        self::assertSame(InteractionActorMetadata::DEFAULT_WIDTH, $metadata[1]->value);
        self::assertSame(InteractionActorMetadata::DEFAULT_HEIGHT, $metadata[2]->value);
    }

    public function testInteractionSizeUpdateContainsOnlyBounds(): void
    {
        $metadata = InteractionActorMetadata::size(4.0, 1.5);

        self::assertSame([53, 54], self::ids($metadata));
        self::assertSame([
            ActorMetadata::TYPE_FLOAT,
            ActorMetadata::TYPE_FLOAT,
        ], self::types($metadata));
        self::assertSame(4.0, $metadata[0]->value);
        self::assertSame(1.5, $metadata[1]->value);
    }

    public function testTextDisplaySetActorDataKnownVector(): void
    {
        $packet = new SetActorDataPacket(
            UnsignedLong::fromInt(10),
            UnsignedLong::fromInt(20),
            TextDisplayActorMetadata::baseline('Hi', -604749536),
        );

        $wire = hex2bin(
            '0a0700070780800a020202bffbddc0040404040248692603030ad7233c'
            . '350303000000003603030000000051000001000014',
        );
        self::assertIsString($wire);
        self::assertSame($wire, $packet->encode());
        self::assertEquals($packet, SetActorDataPacket::decode($wire));
    }

    public function testDisplayAndInteractionMetadataRemainValidInActorConversation(): void
    {
        $display = new AddActorPacket(
            actorUniqueId: 10,
            runtimeEntityId: UnsignedLong::fromInt(10),
            identifier: TextDisplayActorMetadata::IDENTIFIER,
            x: 1.5,
            y: 65.0,
            z: -2.5,
            motionX: 0.0,
            motionY: 0.0,
            motionZ: 0.0,
            pitch: 0.0,
            yaw: 0.0,
            headYaw: 0.0,
            bodyYaw: 0.0,
            metadata: TextDisplayActorMetadata::baseline('Welcome', -604749536),
        );
        self::assertEquals($display, AddActorPacket::decode($display->encode()));

        $textUpdate = new SetActorDataPacket(
            UnsignedLong::fromInt(10),
            UnsignedLong::fromInt(20),
            [TextDisplayActorMetadata::text('Updated')],
        );
        self::assertEquals($textUpdate, SetActorDataPacket::decode($textUpdate->encode()));

        $interaction = new AddActorPacket(
            actorUniqueId: 11,
            runtimeEntityId: UnsignedLong::fromInt(11),
            identifier: InteractionActorMetadata::IDENTIFIER,
            x: 1.5,
            y: 65.0,
            z: -2.5,
            motionX: 0.0,
            motionY: 0.0,
            motionZ: 0.0,
            pitch: 0.0,
            yaw: 0.0,
            headYaw: 0.0,
            bodyYaw: 0.0,
            metadata: InteractionActorMetadata::baseline(),
        );
        self::assertEquals($interaction, AddActorPacket::decode($interaction->encode()));
    }

    /** @return iterable<string, array{float, float}> */
    public static function invalidInteractionDimensions(): iterable
    {
        yield 'zero width' => [0.0, 1.0];
        yield 'negative height' => [1.0, -1.0];
        yield 'non-finite width' => [INF, 1.0];
        yield 'not-a-number height' => [1.0, NAN];
        yield 'float32-underflow width' => [PHP_FLOAT_MIN, 1.0];
        yield 'oversized height' => [1.0, InteractionActorMetadata::MAXIMUM_DIMENSION + 0.01];
    }

    #[DataProvider('invalidInteractionDimensions')]
    public function testInteractionBoundsRejectInvalidDimensions(float $width, float $height): void
    {
        $this->expectException(InvalidValueException::class);
        InteractionActorMetadata::baseline($width, $height);
    }

    /**
     * @param list<ActorMetadata> $metadata
     * @return list<int>
     */
    private static function ids(array $metadata): array
    {
        return array_map(static fn(ActorMetadata $entry): int => $entry->id, $metadata);
    }

    /**
     * @param list<ActorMetadata> $metadata
     * @return list<int>
     */
    private static function types(array $metadata): array
    {
        return array_map(static fn(ActorMetadata $entry): int => $entry->type, $metadata);
    }
}
