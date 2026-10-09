<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Tests\Packet;

use Bedriox\Protocol\Exception\InvalidValueException;
use Bedriox\Protocol\Packet\ActorMetadata;
use Bedriox\Protocol\Packet\HorseActorMetadata;
use PHPUnit\Framework\TestCase;

final class HorseActorMetadataTest extends TestCase
{
    public function testJumpDurationUsesTheCurrentTypedMetadataField(): void
    {
        $metadata = HorseActorMetadata::jumpDuration(0);

        self::assertSame(10, $metadata->id);
        self::assertSame(ActorMetadata::TYPE_BYTE, $metadata->type);
        self::assertSame(0, $metadata->value);
    }

    public function testJumpDurationRejectsValuesOutsideTheWireByte(): void
    {
        $this->expectException(InvalidValueException::class);
        HorseActorMetadata::jumpDuration(256);
    }
}
