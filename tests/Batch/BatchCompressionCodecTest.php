<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Tests\Batch;

use PHPUnit\Framework\TestCase;
use Bedriox\Protocol\Batch\BatchCompressionCodec;
use Bedriox\Protocol\Batch\BatchLimits;
use Bedriox\Protocol\Batch\BedrockBatch;
use Bedriox\Protocol\Batch\BedrockBatchCodec;
use Bedriox\Protocol\Batch\CompressionMode;
use Bedriox\Protocol\Exception\BufferOverflowException;
use Bedriox\Protocol\Exception\CodecException;
use Bedriox\Protocol\Exception\MalformedDataException;
use Bedriox\Protocol\Packet\PacketFrame;
use Bedriox\Protocol\Packet\PacketHeader;

final class BatchCompressionCodecTest extends TestCase
{
    private const string RAW_BATCH = "\x02\x01\xaa\x01\x02";
    private const string ZLIB_VECTOR = "\x78\xda\x63\x62\x5c\xc5\xc8\x04\x00\x02\x15\x00\xb1";
    private const string RAW_DEFLATE_VECTOR = "\x63\x62\x5c\xc5\xc8\x04\x00";

    public function testIndependentCompressionAndEnvelopeVectors(): void
    {
        $limits = new BatchLimits();
        self::assertSame(self::RAW_BATCH, BatchCompressionCodec::decode(self::ZLIB_VECTOR, CompressionMode::Zlib, $limits));
        self::assertSame(
            self::RAW_BATCH,
            BatchCompressionCodec::decode("\x00" . self::RAW_DEFLATE_VECTOR, CompressionMode::NegotiatedZlib, $limits),
        );

        $packets = [
            new PacketFrame(new PacketHeader(1), "\xaa"),
            new PacketFrame(new PacketHeader(2), ''),
        ];
        self::assertSame(
            'feff0201aa0102',
            bin2hex(BedrockBatchCodec::encode(new BedrockBatch($packets, CompressionMode::PrefixedNone), $limits)),
        );
        self::assertEquals(
            $packets,
            BedrockBatchCodec::decode("\xfe\x00" . self::RAW_DEFLATE_VECTOR, CompressionMode::NegotiatedZlib, $limits)->packets,
        );
    }

    public function testAllModesRoundTripDeterministically(): void
    {
        $limits = new BatchLimits();
        foreach (CompressionMode::cases() as $mode) {
            $encoded = BatchCompressionCodec::encode(self::RAW_BATCH, $mode, $limits);
            self::assertSame(self::RAW_BATCH, BatchCompressionCodec::decode($encoded, $mode, $limits));
        }
    }

    public function testNegotiatedZlibUsesAndEnforcesThresholdPerBatch(): void
    {
        $limits = new BatchLimits();
        self::assertSame(
            "\xff" . self::RAW_BATCH,
            BatchCompressionCodec::encode(self::RAW_BATCH, CompressionMode::NegotiatedZlib, $limits, 6),
        );
        self::assertSame(
            self::RAW_BATCH,
            BatchCompressionCodec::decode("\xff" . self::RAW_BATCH, CompressionMode::NegotiatedZlib, $limits, 6),
        );
        self::assertSame(
            "\x00" . self::RAW_DEFLATE_VECTOR,
            BatchCompressionCodec::encode(self::RAW_BATCH, CompressionMode::NegotiatedZlib, $limits, 5),
        );
        self::assertSame(
            self::RAW_BATCH,
            BatchCompressionCodec::decode("\x00" . self::RAW_DEFLATE_VECTOR, CompressionMode::NegotiatedZlib, $limits, 5),
        );
        self::assertSame(
            "\x00" . self::RAW_DEFLATE_VECTOR,
            BatchCompressionCodec::encode(self::RAW_BATCH, CompressionMode::NegotiatedZlib, $limits, 0),
        );
        self::assertSame(
            self::RAW_BATCH,
            BatchCompressionCodec::decode("\x00" . self::RAW_DEFLATE_VECTOR, CompressionMode::NegotiatedZlib, $limits, 0),
        );

        foreach ([
            ["\xff" . self::RAW_BATCH, 5],
            ["\x00" . self::RAW_DEFLATE_VECTOR, 6],
            ["\xff" . self::RAW_BATCH, 0],
        ] as [$encoded, $threshold]) {
            try {
                BatchCompressionCodec::decode($encoded, CompressionMode::NegotiatedZlib, $limits, $threshold);
                self::fail('Batch violating the negotiated zlib threshold was accepted.');
            } catch (MalformedDataException) {
            }
        }
        $this->addToAssertionCount(3);
    }

    public function testCompressedTruncationAndTrailingBytesFail(): void
    {
        for ($length = 0; $length < \strlen(self::ZLIB_VECTOR); ++$length) {
            try {
                BatchCompressionCodec::decode(\substr(self::ZLIB_VECTOR, 0, $length), CompressionMode::Zlib, new BatchLimits());
                self::fail("Compressed truncation at $length was accepted.");
            } catch (CodecException) {
            }
        }

        $this->expectException(MalformedDataException::class);
        BatchCompressionCodec::decode(self::ZLIB_VECTOR . "\x00", CompressionMode::Zlib, new BatchLimits());
    }

    public function testWrongOrMissingAlgorithmPrefixFails(): void
    {
        foreach (['', "\x01abc", "\x02abc"] as $encoded) {
            try {
                BatchCompressionCodec::decode($encoded, CompressionMode::NegotiatedZlib, new BatchLimits());
                self::fail('Invalid algorithm prefix was accepted.');
            } catch (MalformedDataException) {
            }
        }
        $this->addToAssertionCount(3);
    }

    public function testInputOutputAndRatioBombLimits(): void
    {
        $bomb = gzdeflate(str_repeat('A', 20_000), 9);
        self::assertIsString($bomb);
        try {
            BatchCompressionCodec::decode(
                "\x00" . $bomb,
                CompressionMode::NegotiatedZlib,
                new BatchLimits(maximumDecompressedBytes: 1_000, maximumPacketBytes: 1_000),
            );
            self::fail('Decompression size bomb was accepted.');
        } catch (BufferOverflowException) {
        }

        try {
            BatchCompressionCodec::decode(
                "\x00" . $bomb,
                CompressionMode::NegotiatedZlib,
                new BatchLimits(maximumDecompressedBytes: 30_000, maximumCompressionRatio: 2, maximumPacketBytes: 1_000),
            );
            self::fail('Decompression ratio bomb was accepted.');
        } catch (BufferOverflowException) {
        }

        $this->expectException(BufferOverflowException::class);
        BatchCompressionCodec::decode('12345', CompressionMode::Uncompressed, new BatchLimits(maximumInputBytes: 4));
    }

    public function testMarkerAndBatchTrailingGarbageFail(): void
    {
        foreach ([self::RAW_BATCH, "\xff" . self::RAW_BATCH, "\xfe\xff" . self::RAW_BATCH . "\x00"] as $encoded) {
            try {
                BedrockBatchCodec::decode($encoded, CompressionMode::PrefixedNone, new BatchLimits());
                self::fail('Invalid envelope or trailing batch garbage was accepted.');
            } catch (CodecException) {
            }
        }
        $this->addToAssertionCount(3);
    }

    public function testSeededPacketBatchPropertyRoundTrips(): void
    {
        $state = 0x1234_5678;
        $packets = [];
        for ($index = 0; $index < 200; ++$index) {
            $state = (int) (($state * 1_103_515_245 + 12_345) & 0x7fff_ffff);
            $packetId = $state & 0x3ff;
            $length = ($state >> 10) % 32;
            $payload = '';
            for ($byte = 0; $byte < $length; ++$byte) {
                $state = (int) (($state * 1_103_515_245 + 12_345) & 0x7fff_ffff);
                $payload .= \chr($state & 0xff);
            }
            $packets[] = new PacketFrame(new PacketHeader($packetId, $index & 3, ($index >> 2) & 3), $payload);
        }

        $limits = new BatchLimits(maximumPackets: 200);
        foreach (CompressionMode::cases() as $mode) {
            $encoded = BedrockBatchCodec::encode(new BedrockBatch($packets, $mode), $limits);
            self::assertEquals($packets, BedrockBatchCodec::decode($encoded, $mode, $limits)->packets);
        }
    }

    public function testDeterministicMalformedCompressionFuzzStaysBounded(): void
    {
        $state = 0x51f1_5eed;
        $limits = new BatchLimits(
            maximumInputBytes: 256,
            maximumDecompressedBytes: 1_024,
            maximumCompressionRatio: 8,
            maximumPacketBytes: 1_024,
        );
        for ($case = 0; $case < 200; ++$case) {
            $length = ($case % 64) + 1;
            $bytes = '';
            for ($index = 0; $index < $length; ++$index) {
                $state = (int) (($state * 1_103_515_245 + 12_345) & 0x7fff_ffff);
                $bytes .= \chr($state & 0xff);
            }
            try {
                $output = BatchCompressionCodec::decode($bytes, CompressionMode::Zlib, $limits);
                self::assertLessThanOrEqual($limits->maximumDecompressedBytes, \strlen($output));
            } catch (CodecException) {
                $this->addToAssertionCount(1);
            }
        }
    }
}
