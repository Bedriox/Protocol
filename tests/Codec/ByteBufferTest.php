<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Tests\Codec;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Bedriox\Protocol\Codec\ByteBufferReader;
use Bedriox\Protocol\Codec\ByteBufferWriter;
use Bedriox\Protocol\Exception\BufferOverflowException;
use Bedriox\Protocol\Exception\BufferUnderflowException;
use Bedriox\Protocol\Exception\InvalidValueException;
use Bedriox\Protocol\Exception\MalformedDataException;
use Bedriox\Protocol\Value\UnsignedLong;

final class ByteBufferTest extends TestCase
{
    public function testReaderAndWriterAreImmutable(): void
    {
        $empty = ByteBufferWriter::withCapacity(2);
        $written = $empty->writeUnsignedByte(42);
        self::assertSame('', $empty->toString());
        self::assertSame("\x2a", $written->toString());

        $reader = ByteBufferReader::fromString("\x2a\x2b", 2);
        $read = $reader->readUnsignedByte();
        self::assertSame(0, $reader->offset());
        self::assertSame(42, $read->value);
        self::assertSame(1, $read->reader->offset());
    }

    public function testAllFixedWidthTypesRoundTripAtBoundaries(): void
    {
        $unsignedLong = new UnsignedLong(0xffffffff, 0xffffffff);
        $writer = ByteBufferWriter::withCapacity(128)
            ->writeUnsignedByte(0xff)
            ->writeUnsignedShortLE(0xffff)
            ->writeSignedShortLE(-0x8000)
            ->writeUnsignedIntLE(0xffffffff)
            ->writeSignedIntLE(-0x80000000)
            ->writeUnsignedLongLE($unsignedLong)
            ->writeSignedLongLE(PHP_INT_MIN)
            ->writeFloatLE(1.5)
            ->writeDoubleLE(-1234.25);

        $reader = ByteBufferReader::fromString($writer->toString(), 128);
        $byte = $reader->readUnsignedByte();
        $unsignedShort = $byte->reader->readUnsignedShortLE();
        $signedShort = $unsignedShort->reader->readSignedShortLE();
        $unsignedInt = $signedShort->reader->readUnsignedIntLE();
        $signedInt = $unsignedInt->reader->readSignedIntLE();
        $readUnsignedLong = $signedInt->reader->readUnsignedLongLE();
        $signedLong = $readUnsignedLong->reader->readSignedLongLE();
        $float = $signedLong->reader->readFloatLE();
        $double = $float->reader->readDoubleLE();

        self::assertSame(0xff, $byte->value);
        self::assertSame(0xffff, $unsignedShort->value);
        self::assertSame(-0x8000, $signedShort->value);
        self::assertSame(0xffffffff, $unsignedInt->value);
        self::assertSame(-0x80000000, $signedInt->value);
        self::assertTrue($unsignedLong->equals($readUnsignedLong->value));
        self::assertSame(PHP_INT_MIN, $signedLong->value);
        self::assertSame(1.5, $float->value);
        self::assertSame(-1234.25, $double->value);
        self::assertTrue($double->reader->isAtEnd());
    }

    /** @return iterable<string, array{string}> */
    public static function validStrings(): iterable
    {
        yield 'empty' => [''];
        yield 'ascii' => ['Bedriox'];
        yield 'multibyte' => ['世界 🌍'];
        yield 'embedded null' => ["a\0b"];
    }

    #[DataProvider('validStrings')]
    public function testBoundedUtf8StringRoundTrip(string $value): void
    {
        $writer = ByteBufferWriter::withCapacity(128)->writeString($value, strlen($value));
        $read = ByteBufferReader::fromString($writer->toString(), 128)->readString(strlen($value));
        self::assertSame($value, $read->value);
        self::assertTrue($read->reader->isAtEnd());
    }

    public function testReaderInputLimitIsEnforced(): void
    {
        $this->expectException(BufferOverflowException::class);
        ByteBufferReader::fromString('ab', 1);
    }

    public function testWriterCapacityIsEnforcedAtomically(): void
    {
        $writer = ByteBufferWriter::withCapacity(1);
        try {
            $writer->writeBytes('ab');
            self::fail('Expected buffer overflow.');
        } catch (BufferOverflowException) {
            self::assertSame('', $writer->toString());
        }
    }

    public function testFixedWidthTruncationIsRejected(): void
    {
        $this->expectException(BufferUnderflowException::class);
        ByteBufferReader::fromString("\x01\x02\x03", 3)->readUnsignedIntLE();
    }

    public function testEveryFixedWidthReaderRejectsTruncation(): void
    {
        $cases = [
            static fn () => ByteBufferReader::fromString('', 0)->readUnsignedByte(),
            static fn () => ByteBufferReader::fromString("\x00", 1)->readUnsignedShortLE(),
            static fn () => ByteBufferReader::fromString("\x00", 1)->readSignedShortLE(),
            static fn () => ByteBufferReader::fromString(str_repeat("\x00", 3), 3)->readUnsignedIntLE(),
            static fn () => ByteBufferReader::fromString(str_repeat("\x00", 3), 3)->readSignedIntLE(),
            static fn () => ByteBufferReader::fromString(str_repeat("\x00", 7), 7)->readUnsignedLongLE(),
            static fn () => ByteBufferReader::fromString(str_repeat("\x00", 7), 7)->readSignedLongLE(),
            static fn () => ByteBufferReader::fromString(str_repeat("\x00", 3), 3)->readFloatLE(),
            static fn () => ByteBufferReader::fromString(str_repeat("\x00", 7), 7)->readDoubleLE(),
        ];

        foreach ($cases as $case) {
            try {
                $case();
                self::fail('Expected fixed-width input truncation.');
            } catch (BufferUnderflowException) {
                self::addToAssertionCount(1);
            }
        }
    }

    public function testFixedWidthWriterRangeChecks(): void
    {
        $writer = ByteBufferWriter::withCapacity(64);
        $cases = [
            static fn () => $writer->writeUnsignedByte(0x100),
            static fn () => $writer->writeUnsignedShortLE(-1),
            static fn () => $writer->writeSignedShortLE(0x8000),
            static fn () => $writer->writeUnsignedIntLE(-1),
            static fn () => $writer->writeSignedIntLE(0x80000000),
        ];

        foreach ($cases as $case) {
            try {
                $case();
                self::fail('Expected fixed-width range rejection.');
            } catch (InvalidValueException) {
                self::addToAssertionCount(1);
            }
        }
        self::assertSame('', $writer->toString());
    }

    public function testStringDeclaredLengthIsBoundedBeforeContentRead(): void
    {
        $this->expectException(MalformedDataException::class);
        ByteBufferReader::fromString("\x05hello", 6)->readString(4);
    }

    public function testTruncatedStringIsRejected(): void
    {
        $this->expectException(BufferUnderflowException::class);
        ByteBufferReader::fromString("\x05hi", 3)->readString(5);
    }

    public function testInvalidUtf8IsRejectedOnRead(): void
    {
        $this->expectException(MalformedDataException::class);
        ByteBufferReader::fromString("\x01\xff", 2)->readString(1);
    }

    public function testInvalidUtf8IsRejectedOnWrite(): void
    {
        $this->expectException(InvalidValueException::class);
        ByteBufferWriter::withCapacity(8)->writeString("\xff", 1);
    }

    public function testVarIntegerFacadeRoundTrips(): void
    {
        $unsignedLong = new UnsignedLong(0xffffffff, 0xffffffff);
        $writer = ByteBufferWriter::withCapacity(64)
            ->writeUnsignedVarInt(0xffffffff)
            ->writeSignedVarInt(-0x80000000)
            ->writeUnsignedVarLong($unsignedLong)
            ->writeSignedVarLong(PHP_INT_MIN);
        $reader = ByteBufferReader::fromString($writer->toString(), 64);
        $uint = $reader->readUnsignedVarInt();
        $sint = $uint->reader->readSignedVarInt();
        $ulong = $sint->reader->readUnsignedVarLong();
        $slong = $ulong->reader->readSignedVarLong();

        self::assertSame(0xffffffff, $uint->value);
        self::assertSame(-0x80000000, $sint->value);
        self::assertTrue($unsignedLong->equals($ulong->value));
        self::assertSame(PHP_INT_MIN, $slong->value);
        self::assertTrue($slong->reader->isAtEnd());
    }
}
