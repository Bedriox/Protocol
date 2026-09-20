<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

use Bedriox\Protocol\Codec\ByteBufferReader;
use Bedriox\Protocol\Codec\ByteBufferWriter;
use Bedriox\Protocol\Exception\MalformedDataException;

final class CommandWireCodec
{
    private const int ARGUMENT_VALID = 0x100000;

    public static function writeOrigin(ByteBufferWriter $writer, CommandOrigin $origin): ByteBufferWriter
    {
        return $writer->writeString($origin->type->value, CodecSupport::MAX_SHORT_STRING_BYTES)
            ->writeBytes(CodecSupport::uuidToWire($origin->uuid))
            ->writeString($origin->requestId, CodecSupport::MAX_SHORT_STRING_BYTES)
            ->writeSignedLongLE($origin->playerId);
    }

    /** @return array{CommandOrigin, ByteBufferReader} */
    public static function readOrigin(ByteBufferReader $reader): array
    {
        $type = $reader->readString(CodecSupport::MAX_SHORT_STRING_BYTES);
        $originType = CommandOriginType::tryFrom($type->value);
        if ($originType === null) {
            throw new MalformedDataException('Command origin type is unknown.');
        }
        $uuid = $type->reader->readBytes(16);
        $requestId = $uuid->reader->readString(CodecSupport::MAX_SHORT_STRING_BYTES);
        $playerId = $requestId->reader->readSignedLongLE();
        return [
            new CommandOrigin($originType, CodecSupport::uuidFromWire($uuid->value), $requestId->value, $playerId->value),
            $playerId->reader,
        ];
    }

    public static function argumentSymbol(CommandArgumentType $type): int
    {
        return self::ARGUMENT_VALID | $type->value;
    }

    public static function argumentType(int $symbol): CommandArgumentType
    {
        if (($symbol & self::ARGUMENT_VALID) === 0 || ($symbol & ~self::ARGUMENT_VALID) > 0xfffff) {
            throw new MalformedDataException('Command argument symbol is unsupported.');
        }
        $type = CommandArgumentType::tryFrom($symbol & ~self::ARGUMENT_VALID);
        if ($type === null) {
            throw new MalformedDataException('Command argument type is unknown.');
        }
        return $type;
    }
}
