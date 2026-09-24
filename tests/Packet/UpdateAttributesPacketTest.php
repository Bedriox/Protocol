<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Tests\Packet;

use Bedriox\Protocol\Exception\InvalidValueException;
use Bedriox\Protocol\Packet\BedrockPacketCodec;
use Bedriox\Protocol\Packet\PacketIds;
use Bedriox\Protocol\Packet\PlayerAttribute;
use Bedriox\Protocol\Packet\UpdateAttributesPacket;
use Bedriox\Protocol\Value\UnsignedLong;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class UpdateAttributesPacketTest extends TestCase
{
    private const string NUTRITION_VECTOR = '0702000000000000a04100005041000000000000a0410000a041176d696e6563726166743a706c617965722e68756e67657200000000000000a04100009040000000000000a0410000a0411b6d696e6563726166743a706c617965722e73617475726174696f6e007b';

    public function testNutritionFactoryUsesOnlyClientVisibleBoundedAttributes(): void
    {
        $packet = UpdateAttributesPacket::nutrition(
            UnsignedLong::fromInt(7),
            13.0,
            4.5,
            UnsignedLong::fromInt(123),
        );

        self::assertSame(PacketIds::UPDATE_ATTRIBUTES, BedrockPacketCodec::packetId($packet));
        self::assertSame(self::NUTRITION_VECTOR, bin2hex($packet->encode()));
        self::assertCount(2, $packet->attributes);
        self::assertSame(PlayerAttribute::HUNGER, $packet->attributes[0]->name);
        self::assertSame(PlayerAttribute::SATURATION, $packet->attributes[1]->name);
        self::assertSame(13.0, $packet->attributes[0]->value);
        self::assertSame(4.5, $packet->attributes[1]->value);
        self::assertSame(20.0, $packet->attributes[1]->default);
    }

    public function testDynamicHealthFactoryRetainsItsMaximum(): void
    {
        $attribute = PlayerAttribute::health(7.5, 40.0);

        self::assertSame(PlayerAttribute::HEALTH, $attribute->name);
        self::assertSame(7.5, $attribute->value);
        self::assertSame(40.0, $attribute->maximum);
        self::assertSame(40.0, $attribute->defaultMaximum);
    }

    public function testAttributeRejectsInconsistentDefaultBounds(): void
    {
        $this->expectException(InvalidValueException::class);
        new PlayerAttribute('minecraft:test', 0.0, 20.0, 10.0, 5.0, 4.0, 4.5);
    }

    #[DataProvider('invalidNutritionValues')]
    public function testNutritionRejectsNonFiniteAndOutOfRangeValues(float $hunger, float $saturation): void
    {
        $this->expectException(InvalidValueException::class);
        UpdateAttributesPacket::nutrition(UnsignedLong::fromInt(1), $hunger, $saturation);
    }

    /** @return iterable<string, array{float, float}> */
    public static function invalidNutritionValues(): iterable
    {
        yield 'negative hunger' => [-0.1, 0.0];
        yield 'excess hunger' => [20.1, 0.0];
        yield 'negative saturation' => [0.0, -0.1];
        yield 'excess saturation' => [0.0, 20.1];
        yield 'non-finite hunger' => [INF, 0.0];
        yield 'non-finite saturation' => [0.0, NAN];
    }
}
