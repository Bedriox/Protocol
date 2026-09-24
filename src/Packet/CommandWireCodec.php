<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

use Bedriox\Protocol\Codec\ByteBufferReader;
use Bedriox\Protocol\Codec\ByteBufferWriter;
use Bedriox\Protocol\Exception\InvalidValueException;
use Bedriox\Protocol\Exception\MalformedDataException;

final class CommandWireCodec
{
    private const int ARGUMENT_VALID = 0x100000;
    private const int ARGUMENT_ENUM = 0x200000;
    private const int ARGUMENT_SOFT_ENUM = 0x4000000;
    private const int ARGUMENT_POSTFIX = 0x1000000;

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

    /**
     * @param list<CommandEnum> $hardEnums
     * @param list<CommandEnum> $softEnums
     */
    public static function parameterSymbol(CommandArgumentType|CommandEnum $type, array $hardEnums, array $softEnums): int
    {
        if ($type instanceof CommandArgumentType) {
            return self::argumentSymbol($type);
        }
        $enums = $type->soft ? $softEnums : $hardEnums;
        foreach ($enums as $index => $enum) {
            if ($enum == $type) {
                return self::ARGUMENT_VALID | ($type->soft ? self::ARGUMENT_SOFT_ENUM : self::ARGUMENT_ENUM) | $index;
            }
        }
        throw new InvalidValueException('Command parameter enum is absent from its table.');
    }

    /**
     * @param list<CommandEnum> $hardEnums
     * @param list<CommandEnum> $softEnums
     */
    public static function parameterType(int $symbol, array $hardEnums, array $softEnums): CommandArgumentType|CommandEnum
    {
        if (($symbol & self::ARGUMENT_POSTFIX) !== 0) {
            throw new MalformedDataException('Command postfix parameters are unsupported.');
        }
        if (($symbol & self::ARGUMENT_VALID) === 0) {
            throw new MalformedDataException('Command argument symbol is not marked valid.');
        }
        if (($symbol & self::ARGUMENT_SOFT_ENUM) !== 0) {
            if (($symbol & self::ARGUMENT_ENUM) !== 0) {
                throw new MalformedDataException('Command argument symbol combines hard and soft enum flags.');
            }
            $index = $symbol & ~(self::ARGUMENT_VALID | self::ARGUMENT_SOFT_ENUM);
            if (!isset($softEnums[$index])) {
                throw new MalformedDataException('Command argument references an unknown soft enum.');
            }
            return $softEnums[$index];
        }
        if (($symbol & self::ARGUMENT_ENUM) !== 0) {
            $index = $symbol & ~(self::ARGUMENT_VALID | self::ARGUMENT_ENUM);
            if (!isset($hardEnums[$index])) {
                throw new MalformedDataException('Command argument references an unknown hard enum.');
            }
            return $hardEnums[$index];
        }
        return self::argumentType($symbol);
    }
}
