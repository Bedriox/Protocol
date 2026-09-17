<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Tests\Codec;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Bedriox\Protocol\Codec\SignedVarInt;
use Bedriox\Protocol\Codec\SignedVarLong;
use Bedriox\Protocol\Codec\UnsignedVarInt;
use Bedriox\Protocol\Codec\UnsignedVarLong;
use Bedriox\Protocol\Exception\BufferUnderflowException;
use Bedriox\Protocol\Exception\InvalidValueException;
use Bedriox\Protocol\Exception\MalformedDataException;
use Bedriox\Protocol\Value\UnsignedLong;

final class VarIntegerTest extends TestCase
{
    /** @return iterable<string, array{int}> */
    public static function signedIntValues(): iterable
    {
        foreach ([-0x80000000, -16384, -64, -1, 0, 1, 63, 64, 16384, 0x7fffffff] as $value) {
            yield (string) $value => [$value];
        }
    }

    #[DataProvider('signedIntValues')]
    public function testSignedVarIntRoundTrip(int $value): void
    {
        $encoded = SignedVarInt::encode($value);
        self::assertSame($value, SignedVarInt::decode($encoded)['value']);
    }

    /** @return iterable<string, array{int}> */
    public static function signedLongValues(): iterable
    {
        foreach ([PHP_INT_MIN, -0x100000000, -1, 0, 1, 0x100000000, PHP_INT_MAX] as $value) {
            yield (string) $value => [$value];
        }
    }

    #[DataProvider('signedLongValues')]
    public function testSignedVarLongRoundTrip(int $value): void
    {
        $encoded = SignedVarLong::encode($value);
        self::assertSame($value, SignedVarLong::decode($encoded)['value']);
    }

    /** @return iterable<string, array{UnsignedLong}> */
    public static function unsignedLongValues(): iterable
    {
        yield 'zero' => [new UnsignedLong(0, 0)];
        yield 'seven bits' => [new UnsignedLong(0, 0x7f)];
        yield '32-bit maximum' => [new UnsignedLong(0, 0xffffffff)];
        yield 'high limb begins' => [new UnsignedLong(1, 0)];
        yield 'signed boundary' => [new UnsignedLong(0x80000000, 0)];
        yield '64-bit maximum' => [new UnsignedLong(0xffffffff, 0xffffffff)];
    }

    #[DataProvider('unsignedLongValues')]
    public function testUnsignedVarLongRoundTrip(UnsignedLong $value): void
    {
        $encoded = UnsignedVarLong::encode($value);
        $decoded = UnsignedVarLong::decode($encoded);
        self::assertTrue($value->equals($decoded['value']));
        self::assertSame(strlen($encoded), $decoded['bytes']);
    }

    public function testUnsignedLongComparisonDoesNotUseSignedConversion(): void
    {
        $signedMaximum = new UnsignedLong(0x7fffffff, 0xffffffff);
        $signedBoundary = new UnsignedLong(0x80000000, 0);
        $maximum = new UnsignedLong(0xffffffff, 0xffffffff);

        self::assertSame(-1, $signedMaximum->compareTo($signedBoundary));
        self::assertSame(1, $signedBoundary->compareTo($signedMaximum));
        self::assertSame(-1, $signedBoundary->compareTo($maximum));
        self::assertSame(0, $maximum->compareTo(new UnsignedLong(0xffffffff, 0xffffffff)));
    }

    public function testOffsetsAreHonored(): void
    {
        self::assertSame(300, UnsignedVarInt::decode("\xaa" . UnsignedVarInt::encode(300), 1)['value']);
        self::assertTrue(
            (new UnsignedLong(1, 2))->equals(
                UnsignedVarLong::decode("\xaa" . UnsignedVarLong::encode(new UnsignedLong(1, 2)), 1)['value'],
            ),
        );
    }

    public function testUnsignedVarIntOverflowIsRejected(): void
    {
        $this->expectException(MalformedDataException::class);
        UnsignedVarInt::decode("\xff\xff\xff\xff\x10");
    }

    public function testUnsignedVarLongOverflowIsRejected(): void
    {
        $this->expectException(MalformedDataException::class);
        UnsignedVarLong::decode(str_repeat("\xff", 9) . "\x02");
    }

    public function testVarIntegerContinuationOverflowIsRejected(): void
    {
        foreach ([str_repeat("\x80", 5), str_repeat("\x80", 10)] as $encoded) {
            try {
                strlen($encoded) === 5
                    ? UnsignedVarInt::decode($encoded)
                    : UnsignedVarLong::decode($encoded);
                self::fail('Expected continuation overflow.');
            } catch (MalformedDataException) {
                self::addToAssertionCount(1);
            }
        }
    }

    public function testNonCanonicalVarIntIsRejected(): void
    {
        $this->expectException(MalformedDataException::class);
        UnsignedVarInt::decode("\x80\x00");
    }

    public function testNonCanonicalVarLongIsRejected(): void
    {
        $this->expectException(MalformedDataException::class);
        UnsignedVarLong::decode("\x80\x00");
    }

    public function testTruncatedVarLongIsRejected(): void
    {
        $this->expectException(BufferUnderflowException::class);
        UnsignedVarLong::decode("\x80");
    }

    public function testOutOfRangeSignedVarIntIsRejected(): void
    {
        $this->expectException(InvalidValueException::class);
        SignedVarInt::encode(0x80000000);
    }
}
