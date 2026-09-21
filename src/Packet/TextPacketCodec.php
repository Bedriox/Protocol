<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

use Bedriox\Protocol\Exception\MalformedDataException;

/** Selects one of the bounded text packet shapes supported by the current protocol. */
final class TextPacketCodec
{
    public static function decode(string $bytes): ChatPacket|SystemTextPacket|TextPacket|TranslatedTextPacket
    {
        [$needsTranslation, $reader] = CodecSupport::readBoolean(CodecSupport::reader($bytes));
        $variantWire = $reader->readUnsignedByte();
        $typeWire = $variantWire->reader->readUnsignedByte();
        $variant = TextPayloadVariant::tryFrom($variantWire->value);
        $type = TextPacketType::tryFrom($typeWire->value);

        return match (true) {
            !$needsTranslation
                && $variant === TextPayloadVariant::AuthorAndMessage
                && $type === TextPacketType::Chat => ChatPacket::decode($bytes),
            !$needsTranslation
                && $variant === TextPayloadVariant::MessageOnly
                && $type === TextPacketType::System => SystemTextPacket::decode($bytes),
            $needsTranslation
                && $variant === TextPayloadVariant::MessageAndParameters
                && $type === TextPacketType::Translation => TranslatedTextPacket::decode($bytes),
            $type !== null => TextPacket::decode($bytes),
            default => throw new MalformedDataException('Text payload shape is not supported by the current codec.'),
        };
    }
}
