<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Batch;

use Bedriox\Protocol\Codec\ByteBufferReader;
use Bedriox\Protocol\Codec\ByteBufferWriter;
use Bedriox\Protocol\Exception\InvalidValueException;
use Bedriox\Protocol\Exception\MalformedDataException;
use Bedriox\Protocol\Packet\PacketFrame;
use Bedriox\Protocol\Packet\PacketFrameCodec;

final class PacketBatchCodec
{
    private function __construct() {}

    /** @param list<PacketFrame> $packets */
    public static function encode(array $packets, BatchLimits $limits): string
    {
        if ($packets === [] || \count($packets) > $limits->maximumPackets) {
            throw new InvalidValueException('Packet count is outside the configured batch limit.');
        }

        $writer = ByteBufferWriter::withCapacity($limits->maximumDecompressedBytes);
        foreach ($packets as $packet) {
            if (!$packet instanceof PacketFrame) {
                throw new InvalidValueException('Batch entries must be packet frames.');
            }
            $encoded = PacketFrameCodec::encode($packet, $limits->maximumPacketBytes);
            $writer = $writer->writeUnsignedVarInt(\strlen($encoded))->writeBytes($encoded);
        }

        return $writer->toString();
    }

    /** @return list<PacketFrame> */
    public static function decode(string $bytes, BatchLimits $limits): array
    {
        $reader = ByteBufferReader::fromString($bytes, $limits->maximumDecompressedBytes);
        $packets = [];
        while (!$reader->isAtEnd()) {
            if (\count($packets) >= $limits->maximumPackets) {
                throw new MalformedDataException('Batch exceeds the configured packet-count limit.');
            }
            $length = $reader->readUnsignedVarInt();
            if ($length->value < 1) {
                throw new MalformedDataException('Batch packet length must be positive.');
            }
            if ($length->value > $limits->maximumPacketBytes) {
                throw new MalformedDataException('Batch packet exceeds its configured byte limit.');
            }
            $encoded = $length->reader->readBytes($length->value);
            $packets[] = PacketFrameCodec::decode($encoded->value, $limits->maximumPacketBytes);
            $reader = $encoded->reader;
        }
        if ($packets === []) {
            throw new MalformedDataException('A Bedrock batch must contain at least one packet.');
        }

        return $packets;
    }
}
