<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

use Bedriox\Protocol\Exception\MalformedDataException;

/** Bedrock AuthorAndMessage/CHAT text variant used by the MVP. */
final readonly class ChatPacket implements Packet
{
    public function __construct(
        public string $sourceName,
        public string $message,
        public string $xuid = '',
        public string $platformChatId = '',
        public string $filteredMessage = '',
    ) {
        CodecSupport::validateString($sourceName, CodecSupport::MAX_PLAYER_NAME_BYTES, 'Chat source');
        CodecSupport::validateString($message, CodecSupport::MAX_CHAT_BYTES, 'Chat message');
        CodecSupport::validateString($xuid, CodecSupport::MAX_SHORT_STRING_BYTES, 'Chat XUID');
        CodecSupport::validateString($platformChatId, CodecSupport::MAX_SHORT_STRING_BYTES, 'Platform chat ID');
        CodecSupport::validateString($filteredMessage, CodecSupport::MAX_CHAT_BYTES, 'Filtered chat message');
        if ($message === '') {
            throw new \Bedriox\Protocol\Exception\InvalidValueException('Chat message cannot be empty.');
        }
    }

    public function packetId(): int { return PacketIds::TEXT; }

    public function encode(): string
    {
        $writer = CodecSupport::writeBoolean(CodecSupport::writer(), false)
            ->writeUnsignedByte(1)
            ->writeUnsignedByte(1)
            ->writeString($this->sourceName, CodecSupport::MAX_PLAYER_NAME_BYTES)
            ->writeString($this->message, CodecSupport::MAX_CHAT_BYTES)
            ->writeString($this->xuid, CodecSupport::MAX_SHORT_STRING_BYTES)
            ->writeString($this->platformChatId, CodecSupport::MAX_SHORT_STRING_BYTES);
        $writer = CodecSupport::writeBoolean($writer, $this->filteredMessage !== '');
        if ($this->filteredMessage !== '') {
            $writer = $writer->writeString($this->filteredMessage, CodecSupport::MAX_CHAT_BYTES);
        }
        return $writer->toString();
    }

    public static function decode(string $bytes): self
    {
        [$translation, $reader] = CodecSupport::readBoolean(CodecSupport::reader($bytes));
        if ($translation) {
            throw new MalformedDataException('MVP chat cannot request translation.');
        }
        $variant = $reader->readUnsignedByte();
        $type = $variant->reader->readUnsignedByte();
        if ($variant->value !== 1 || $type->value !== 1) {
            throw new MalformedDataException('Text payload is not the Bedrock CHAT AuthorAndMessage variant.');
        }
        $source = $type->reader->readString(CodecSupport::MAX_PLAYER_NAME_BYTES);
        $message = $source->reader->readString(CodecSupport::MAX_CHAT_BYTES);
        if ($message->value === '') {
            throw new MalformedDataException('Chat message cannot be empty.');
        }
        $xuid = $message->reader->readString(CodecSupport::MAX_SHORT_STRING_BYTES);
        $platform = $xuid->reader->readString(CodecSupport::MAX_SHORT_STRING_BYTES);
        [$hasFiltered, $reader] = CodecSupport::readBoolean($platform->reader);
        $filtered = '';
        if ($hasFiltered) {
            $read = $reader->readString(CodecSupport::MAX_CHAT_BYTES);
            $filtered = $read->value;
            $reader = $read->reader;
        }
        CodecSupport::requireEnd($reader);
        return new self($source->value, $message->value, $xuid->value, $platform->value, $filtered);
    }
}
