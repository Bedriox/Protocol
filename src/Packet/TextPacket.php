<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

use Bedriox\Protocol\Exception\InvalidValueException;
use Bedriox\Protocol\Exception\MalformedDataException;

/** Remaining protocol-2193 text variants not represented by the dedicated chat/system/translation packets. */
final readonly class TextPacket implements Packet
{
    private const int MAXIMUM_PARAMETERS = 16;

    /** @param list<string> $parameters */
    public function __construct(
        public TextPacketType $type,
        public string $message,
        public string $sourceName = '',
        public array $parameters = [],
        public bool $needsTranslation = false,
        public string $xuid = '',
        public string $platformChatId = '',
        public string $filteredMessage = '',
    ) {
        if (!self::isSupportedType($type)) {
            throw new InvalidValueException('Use the dedicated chat, system, or translated text packet for this text type.');
        }
        CodecSupport::validateString($message, CodecSupport::MAX_SHORT_STRING_BYTES, 'Text message');
        CodecSupport::validateString($sourceName, CodecSupport::MAX_PLAYER_NAME_BYTES, 'Text source');
        CodecSupport::validateCount($parameters, self::MAXIMUM_PARAMETERS, 'Text parameters');
        foreach ($parameters as $parameter) {
            if (!is_string($parameter)) {
                throw new InvalidValueException('Text parameters must be strings.');
            }
            CodecSupport::validateString($parameter, CodecSupport::MAX_SHORT_STRING_BYTES, 'Text parameter');
        }
        CodecSupport::validateString($xuid, CodecSupport::MAX_SHORT_STRING_BYTES, 'Text XUID');
        CodecSupport::validateString($platformChatId, CodecSupport::MAX_SHORT_STRING_BYTES, 'Text platform chat ID');
        CodecSupport::validateString($filteredMessage, CodecSupport::MAX_SHORT_STRING_BYTES, 'Filtered text message');
        if ($message === '') {
            throw new InvalidValueException('Text message cannot be empty.');
        }

        $variant = self::variantFor($type);
        if ($variant !== TextPayloadVariant::AuthorAndMessage && $sourceName !== '') {
            throw new InvalidValueException('Only authored text may contain a source name.');
        }
        if ($variant !== TextPayloadVariant::MessageAndParameters && $parameters !== []) {
            throw new InvalidValueException('Only parameterized text may contain parameters.');
        }
    }

    public static function raw(string $message): self
    {
        return new self(TextPacketType::Raw, $message);
    }

    /** @param list<string> $parameters */
    public static function popup(string $message, array $parameters = []): self
    {
        return new self(TextPacketType::Popup, $message, parameters: $parameters);
    }

    /** @param list<string> $parameters */
    public static function jukeboxPopup(string $message, array $parameters = []): self
    {
        return new self(TextPacketType::JukeboxPopup, $message, parameters: $parameters);
    }

    public static function tip(string $message): self
    {
        return new self(TextPacketType::Tip, $message);
    }

    public static function whisper(string $sourceName, string $message): self
    {
        return new self(TextPacketType::Whisper, $message, $sourceName);
    }

    public static function announcement(string $sourceName, string $message): self
    {
        return new self(TextPacketType::Announcement, $message, $sourceName);
    }

    public static function whisperJson(string $json): self
    {
        return new self(TextPacketType::WhisperJson, $json);
    }

    public static function json(string $json): self
    {
        return new self(TextPacketType::Json, $json);
    }

    public static function announcementJson(string $json): self
    {
        return new self(TextPacketType::AnnouncementJson, $json);
    }

    public function packetId(): int
    {
        return PacketIds::TEXT;
    }

    public function encode(): string
    {
        $variant = self::variantFor($this->type);
        $writer = CodecSupport::writeBoolean(CodecSupport::writer(), $this->needsTranslation)
            ->writeUnsignedByte($variant->value)
            ->writeUnsignedByte($this->type->value);
        if ($variant === TextPayloadVariant::AuthorAndMessage) {
            $writer = $writer->writeString($this->sourceName, CodecSupport::MAX_PLAYER_NAME_BYTES);
        }
        $writer = $writer->writeString($this->message, CodecSupport::MAX_SHORT_STRING_BYTES);
        if ($variant === TextPayloadVariant::MessageAndParameters) {
            $writer = $writer->writeUnsignedVarInt(count($this->parameters));
            foreach ($this->parameters as $parameter) {
                $writer = $writer->writeString($parameter, CodecSupport::MAX_SHORT_STRING_BYTES);
            }
        }
        $writer = $writer->writeString($this->xuid, CodecSupport::MAX_SHORT_STRING_BYTES)
            ->writeString($this->platformChatId, CodecSupport::MAX_SHORT_STRING_BYTES);
        $writer = CodecSupport::writeBoolean($writer, $this->filteredMessage !== '');
        if ($this->filteredMessage !== '') {
            $writer = $writer->writeString($this->filteredMessage, CodecSupport::MAX_SHORT_STRING_BYTES);
        }

        return $writer->toString();
    }

    public static function decode(string $bytes): self
    {
        [$needsTranslation, $reader] = CodecSupport::readBoolean(CodecSupport::reader($bytes));
        $variantWire = $reader->readUnsignedByte();
        $typeWire = $variantWire->reader->readUnsignedByte();
        $variant = TextPayloadVariant::tryFrom($variantWire->value);
        $type = TextPacketType::tryFrom($typeWire->value);
        if ($variant === null || $type === null || !self::isSupportedType($type) || self::variantFor($type) !== $variant) {
            throw new MalformedDataException('Text type does not match its protocol-2193 payload variant.');
        }

        $reader = $typeWire->reader;
        $sourceName = '';
        if ($variant === TextPayloadVariant::AuthorAndMessage) {
            $source = $reader->readString(CodecSupport::MAX_PLAYER_NAME_BYTES);
            $sourceName = $source->value;
            $reader = $source->reader;
        }
        $message = $reader->readString(CodecSupport::MAX_SHORT_STRING_BYTES);
        if ($message->value === '') {
            throw new MalformedDataException('Text message cannot be empty.');
        }
        $reader = $message->reader;
        $parameters = [];
        if ($variant === TextPayloadVariant::MessageAndParameters) {
            $count = $reader->readUnsignedVarInt();
            if ($count->value > self::MAXIMUM_PARAMETERS) {
                throw new MalformedDataException('Text parameter count exceeds its limit.');
            }
            $reader = $count->reader;
            for ($index = 0; $index < $count->value; ++$index) {
                $parameter = $reader->readString(CodecSupport::MAX_SHORT_STRING_BYTES);
                $parameters[] = $parameter->value;
                $reader = $parameter->reader;
            }
        }
        $xuid = $reader->readString(CodecSupport::MAX_SHORT_STRING_BYTES);
        $platform = $xuid->reader->readString(CodecSupport::MAX_SHORT_STRING_BYTES);
        [$hasFiltered, $reader] = CodecSupport::readBoolean($platform->reader);
        $filtered = '';
        if ($hasFiltered) {
            $read = $reader->readString(CodecSupport::MAX_SHORT_STRING_BYTES);
            $filtered = $read->value;
            $reader = $read->reader;
        }
        CodecSupport::requireEnd($reader);

        return new self(
            $type,
            $message->value,
            $sourceName,
            $parameters,
            $needsTranslation,
            $xuid->value,
            $platform->value,
            $filtered,
        );
    }

    private static function isSupportedType(TextPacketType $type): bool
    {
        return !in_array($type, [TextPacketType::Chat, TextPacketType::Translation, TextPacketType::System], true);
    }

    private static function variantFor(TextPacketType $type): TextPayloadVariant
    {
        return match ($type) {
            TextPacketType::Raw, TextPacketType::Tip, TextPacketType::WhisperJson,
            TextPacketType::Json, TextPacketType::AnnouncementJson => TextPayloadVariant::MessageOnly,
            TextPacketType::Chat, TextPacketType::Whisper,
            TextPacketType::Announcement => TextPayloadVariant::AuthorAndMessage,
            TextPacketType::Translation, TextPacketType::Popup,
            TextPacketType::JukeboxPopup => TextPayloadVariant::MessageAndParameters,
            TextPacketType::System => TextPayloadVariant::MessageOnly,
        };
    }
}
