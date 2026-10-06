<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Tests\Packet;

use Bedriox\Protocol\Exception\InvalidValueException;
use Bedriox\Protocol\Packet\ActorMetadata;
use Bedriox\Protocol\Packet\SetActorDataPacket;
use Bedriox\Protocol\Packet\ShulkerActorMetadata;
use Bedriox\Protocol\Packet\ShulkerAttachmentFace;
use Bedriox\Protocol\Value\UnsignedLong;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ShulkerActorMetadataTest extends TestCase
{
    public function testPresentationUsesCurrentLiteralIdsAndWireTypes(): void
    {
        $metadata = ShulkerActorMetadata::presentation(100, ShulkerAttachmentFace::North);

        self::assertSame([64, 65, 66], array_map(
            static fn(ActorMetadata $entry): int => $entry->id,
            $metadata,
        ));
        self::assertSame([
            ActorMetadata::TYPE_INT,
            ActorMetadata::TYPE_BYTE,
            ActorMetadata::TYPE_SHORT,
        ], array_map(
            static fn(ActorMetadata $entry): int => $entry->type,
            $metadata,
        ));
        self::assertSame([100, 2, 1], array_map(
            static fn(ActorMetadata $entry): int|float|string|object => $entry->value,
            $metadata,
        ));
    }

    public function testSetActorDataKnownVectorAndRoundTrip(): void
    {
        $packet = new SetActorDataPacket(
            UnsignedLong::fromInt(42),
            UnsignedLong::fromInt(7),
            ShulkerActorMetadata::presentation(100, ShulkerAttachmentFace::North),
        );

        $wire = hex2bin('2a03400202c801410000024201010100000007');
        self::assertIsString($wire);
        self::assertSame($wire, $packet->encode());
        self::assertEquals($packet, SetActorDataPacket::decode($wire));
    }

    /** @return iterable<string, array{int}> */
    public static function invalidPresentationValues(): iterable
    {
        yield 'negative peek' => [-1];
        yield 'oversized peek' => [101];
    }

    #[DataProvider('invalidPresentationValues')]
    public function testRejectsInvalidPresentationValues(int $peekAmount): void
    {
        $this->expectException(InvalidValueException::class);
        ShulkerActorMetadata::presentation($peekAmount, ShulkerAttachmentFace::Down);
    }
}
