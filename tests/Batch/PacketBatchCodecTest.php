<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Tests\Batch;

use PHPUnit\Framework\TestCase;
use Bedriox\Protocol\Batch\BatchLimits;
use Bedriox\Protocol\Batch\PacketBatchCodec;
use Bedriox\Protocol\Exception\CodecException;
use Bedriox\Protocol\Exception\InvalidValueException;
use Bedriox\Protocol\Exception\MalformedDataException;
use Bedriox\Protocol\Packet\PacketFrame;
use Bedriox\Protocol\Packet\PacketHeader;

final class PacketBatchCodecTest extends TestCase
{
    public function testIndependentLengthPrefixedVector(): void
    {
        $packets = [
            new PacketFrame(new PacketHeader(1), "\xaa"),
            new PacketFrame(new PacketHeader(2), ''),
        ];
        $limits = new BatchLimits();
        $encoded = PacketBatchCodec::encode($packets, $limits);

        self::assertSame('0201aa0102', bin2hex($encoded));
        self::assertEquals($packets, PacketBatchCodec::decode($encoded, $limits));
    }

    public function testEveryTruncationOfKnownBatchFails(): void
    {
        $encoded = hex2bin('0501aabbccdd');
        self::assertIsString($encoded);
        for ($length = 0; $length < \strlen($encoded); ++$length) {
            try {
                PacketBatchCodec::decode(\substr($encoded, 0, $length), new BatchLimits());
                self::fail("Truncation at $length bytes was accepted.");
            } catch (CodecException) {
            }
        }
    }

    public function testZeroLengthTrailingAndNoncanonicalLengthsFail(): void
    {
        foreach (["\x00", "\x01\x01\x00", "\x81\x00\x01"] as $encoded) {
            try {
                PacketBatchCodec::decode($encoded, new BatchLimits());
                self::fail('Malformed batch length was accepted.');
            } catch (MalformedDataException) {
            }
        }
        $this->addToAssertionCount(3);
    }

    public function testPacketCountAndSizeLimitsApplyBeforeSlices(): void
    {
        $twoPackets = hex2bin('01010102');
        self::assertIsString($twoPackets);
        try {
            PacketBatchCodec::decode($twoPackets, new BatchLimits(maximumPackets: 1));
            self::fail('Packet-count overflow was accepted.');
        } catch (MalformedDataException) {
        }

        $this->expectException(MalformedDataException::class);
        PacketBatchCodec::decode("\x02\x01\xaa", new BatchLimits(maximumPacketBytes: 1));
    }

    public function testInvalidEmptyEncodeAndLimitsFail(): void
    {
        try {
            PacketBatchCodec::encode([], new BatchLimits());
            self::fail('Empty batch was encoded.');
        } catch (InvalidValueException) {
        }

        $this->expectException(InvalidValueException::class);
        new BatchLimits(maximumPackets: 0);
    }
}
