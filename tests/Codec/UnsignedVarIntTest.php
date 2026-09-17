<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Tests\Codec;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Bedriox\Protocol\Codec\UnsignedVarInt;
use Bedriox\Protocol\Exception\BufferUnderflowException;

final class UnsignedVarIntTest extends TestCase
{
    /** @return iterable<string, array{int, string}> */
    public static function values(): iterable
    {
        yield 'zero' => [0, "\x00"];
        yield 'one byte maximum' => [127, "\x7f"];
        yield 'two bytes' => [128, "\x80\x01"];
        yield '32-bit maximum' => [0xffffffff, "\xff\xff\xff\xff\x0f"];
    }

    #[DataProvider('values')]
    public function testRoundTrip(int $value, string $expected): void
    {
        self::assertSame($expected, UnsignedVarInt::encode($value));
        self::assertSame(['value' => $value, 'bytes' => strlen($expected)], UnsignedVarInt::decode($expected));
    }

    public function testTruncatedValueIsRejected(): void
    {
        $this->expectException(BufferUnderflowException::class);
        UnsignedVarInt::decode("\x80");
    }
}

