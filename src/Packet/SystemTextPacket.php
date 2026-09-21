<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

use Bedriox\Protocol\Exception\InvalidValueException;
use Bedriox\Protocol\Exception\MalformedDataException;

/** Untranslated server-authored text displayed in the system chat channel. */
final readonly class SystemTextPacket implements Packet
{
    public function __construct(
        public string $message,
        public string $xuid = '',
        public string $platformChatId = '',
        public string $filteredMessage = '',
    ) {
        CodecSupport::validateString($message, CodecSupport::MAX_SHORT_STRING_BYTES, 'System text message');
        CodecSupport::validateString($xuid, CodecSupport::MAX_SHORT_STRING_BYTES, 'System text XUID');
        CodecSupport::validateString($platformChatId, CodecSupport::MAX_SHORT_STRING_BYTES, 'System text platform chat ID');
        CodecSupport::validateString($filteredMessage, CodecSupport::MAX_SHORT_STRING_BYTES, 'Filtered system text message');
        if ($message === '') {
            throw new InvalidValueException('System text message cannot be empty.');
        }
    }

    public function packetId(): int { return PacketIds::TEXT; }

    public function encode(): string
    {
        $writer = CodecSupport::writeBoolean(CodecSupport::writer(), false)
            ->writeUnsignedByte(TextPayloadVariant::MessageOnly->value)
            ->writeUnsignedByte(TextPacketType::System->value)
            ->writeString($this->message, CodecSupport::MAX_SHORT_STRING_BYTES)
            ->writeString($this->xuid, CodecSupport::MAX_SHORT_STRING_BYTES)
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
        if ($needsTranslation) {
            throw new MalformedDataException('System text cannot request translation.');
        }
        $variant = $reader->readUnsignedByte();
        $type = $variant->reader->readUnsignedByte();
        if ($variant->value !== TextPayloadVariant::MessageOnly->value || $type->value !== TextPacketType::System->value) {
            throw new MalformedDataException('Text payload is not the Bedrock SYSTEM MessageOnly variant.');
        }
        $message = $type->reader->readString(CodecSupport::MAX_SHORT_STRING_BYTES);
        if ($message->value === '') {
            throw new MalformedDataException('System text message cannot be empty.');
        }
        $xuid = $message->reader->readString(CodecSupport::MAX_SHORT_STRING_BYTES);
        $platform = $xuid->reader->readString(CodecSupport::MAX_SHORT_STRING_BYTES);
        [$hasFiltered, $reader] = CodecSupport::readBoolean($platform->reader);
        $filtered = '';
        if ($hasFiltered) {
            $read = $reader->readString(CodecSupport::MAX_SHORT_STRING_BYTES);
            $filtered = $read->value;
            $reader = $read->reader;
        }
        CodecSupport::requireEnd($reader);

        return new self($message->value, $xuid->value, $platform->value, $filtered);
    }
}
