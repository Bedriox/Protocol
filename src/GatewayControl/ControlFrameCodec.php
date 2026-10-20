<?php

declare(strict_types=1);

namespace Bedriox\Protocol\GatewayControl;

use Bedriox\Protocol\Codec\ByteBufferReader;
use Bedriox\Protocol\Codec\ByteBufferWriter;
use Bedriox\Protocol\Exception\MalformedDataException;

final class ControlFrameCodec
{
    public const string MAGIC = 'BXGC';
    public const int HEADER_BYTES = 32;
    public const int MAXIMUM_FRAME_BYTES = self::HEADER_BYTES + ControlFrame::MAXIMUM_PAYLOAD_BYTES;

    public static function encode(ControlFrame $frame): string
    {
        $requestId = hex2bin($frame->requestId);
        if (!is_string($requestId) || strlen($requestId) !== 16) {
            throw new \LogicException('Validated Gateway control request ID could not be encoded.');
        }

        return ByteBufferWriter::withCapacity(self::MAXIMUM_FRAME_BYTES)
            ->writeBytes(self::MAGIC)
            ->writeUnsignedByte(ControlFrame::VERSION)
            ->writeUnsignedByte($frame->type->value)
            ->writeUnsignedShortLE($frame->flags)
            ->writeBytes($requestId)
            ->writeUnsignedIntLE($frame->sequence)
            ->writeUnsignedIntLE(strlen($frame->payload))
            ->writeBytes($frame->payload)
            ->toString();
    }

    public static function decode(string $bytes): ControlFrame
    {
        $reader = ByteBufferReader::fromString($bytes, self::MAXIMUM_FRAME_BYTES);
        $magic = $reader->readBytes(4);
        if (!hash_equals(self::MAGIC, $magic->value)) {
            throw new MalformedDataException('Gateway control frame magic is invalid.');
        }
        $version = $magic->reader->readUnsignedByte();
        if ($version->value !== ControlFrame::VERSION) {
            throw new MalformedDataException('Gateway control frame version is unsupported.');
        }
        $type = $version->reader->readUnsignedByte();
        $messageType = ControlMessageType::tryFrom($type->value);
        if ($messageType === null) {
            throw new MalformedDataException('Gateway control message type is unsupported.');
        }
        $flags = $type->reader->readUnsignedShortLE();
        if (($flags->value & ~ControlFrame::FLAG_ENCRYPTED) !== 0) {
            throw new MalformedDataException('Gateway control frame flags are unsupported.');
        }
        $requestId = $flags->reader->readBytes(16);
        $sequence = $requestId->reader->readUnsignedIntLE();
        $length = $sequence->reader->readUnsignedIntLE();
        if ($length->value > ControlFrame::MAXIMUM_PAYLOAD_BYTES) {
            throw new MalformedDataException('Gateway control payload exceeds its byte limit.');
        }
        $payload = $length->reader->readBytes($length->value);
        if (!$payload->reader->isAtEnd()) {
            throw new MalformedDataException('Gateway control frame contains trailing bytes.');
        }

        return new ControlFrame(
            $messageType,
            bin2hex($requestId->value),
            $sequence->value,
            $payload->value,
            $flags->value,
        );
    }
}
