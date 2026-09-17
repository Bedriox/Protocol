<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

use Bedriox\Protocol\Codec\ByteBufferReader;
use Bedriox\Protocol\Codec\UnsignedVarInt;
use Bedriox\Protocol\Exception\BufferOverflowException;
use Bedriox\Protocol\Exception\InvalidValueException;
use Bedriox\Protocol\Exception\MalformedDataException;

final class PacketFrameCodec
{
    private const int HEADER_MASK = 0x3fff;

    private function __construct() {}

    public static function encode(PacketFrame $frame, int $maximumBytes): string
    {
        if ($maximumBytes < 1) {
            throw new InvalidValueException('Packet-frame maximum must be positive.');
        }
        $encoded = UnsignedVarInt::encode($frame->header->packed()) . $frame->payload;
        if (\strlen($encoded) > $maximumBytes) {
            throw new BufferOverflowException('Packet frame exceeds its configured byte limit.');
        }

        return $encoded;
    }

    public static function decode(string $bytes, int $maximumBytes): PacketFrame
    {
        if ($maximumBytes < 1) {
            throw new InvalidValueException('Packet-frame maximum must be positive.');
        }
        $reader = ByteBufferReader::fromString($bytes, $maximumBytes);
        $header = $reader->readUnsignedVarInt();
        if (($header->value & ~self::HEADER_MASK) !== 0) {
            throw new MalformedDataException('Packet header contains reserved bits.');
        }
        $payload = $header->reader->readBytes($header->reader->remaining());

        return new PacketFrame(
            new PacketHeader(
                $header->value & PacketHeader::MAXIMUM_PACKET_ID,
                ($header->value >> 10) & PacketHeader::MAXIMUM_SUBCLIENT_ID,
                ($header->value >> 12) & PacketHeader::MAXIMUM_SUBCLIENT_ID,
            ),
            $payload->value,
        );
    }
}
