<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

use Bedriox\Protocol\Exception\InvalidValueException;
use Bedriox\Protocol\Exception\MalformedDataException;

/** Server-authored translation key and bounded parameter list. */
final readonly class TranslatedTextPacket implements Packet
{
    private const int MAXIMUM_PARAMETERS = 16;

    /** @param list<string> $parameters */
    public function __construct(
        public string $message,
        public array $parameters = [],
        public string $xuid = '',
        public string $platformChatId = '',
        public string $filteredMessage = '',
    ) {
        CodecSupport::validateString($message, CodecSupport::MAX_SHORT_STRING_BYTES, 'Translated text message');
        if ($message === '') {
            throw new InvalidValueException('Translated text message cannot be empty.');
        }
        CodecSupport::validateCount($parameters, self::MAXIMUM_PARAMETERS, 'Translated text parameters');
        foreach ($parameters as $parameter) {
            if (!is_string($parameter)) {
                throw new InvalidValueException('Translated text parameters must be strings.');
            }
            CodecSupport::validateString($parameter, CodecSupport::MAX_SHORT_STRING_BYTES, 'Translated text parameter');
        }
        CodecSupport::validateString($xuid, CodecSupport::MAX_SHORT_STRING_BYTES, 'Translated text XUID');
        CodecSupport::validateString($platformChatId, CodecSupport::MAX_SHORT_STRING_BYTES, 'Translated text platform chat ID');
        CodecSupport::validateString($filteredMessage, CodecSupport::MAX_SHORT_STRING_BYTES, 'Filtered translated text message');
    }

    public function packetId(): int { return PacketIds::TEXT; }

    public function encode(): string
    {
        $writer = CodecSupport::writeBoolean(CodecSupport::writer(), true)
            ->writeUnsignedByte(TextPayloadVariant::MessageAndParameters->value)
            ->writeUnsignedByte(TextPacketType::Translation->value)
            ->writeString($this->message, CodecSupport::MAX_SHORT_STRING_BYTES)
            ->writeUnsignedVarInt(count($this->parameters));
        foreach ($this->parameters as $parameter) {
            $writer = $writer->writeString($parameter, CodecSupport::MAX_SHORT_STRING_BYTES);
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
        if (!$needsTranslation) {
            throw new MalformedDataException('Translated text must request translation.');
        }
        $variant = $reader->readUnsignedByte();
        $type = $variant->reader->readUnsignedByte();
        if ($variant->value !== TextPayloadVariant::MessageAndParameters->value || $type->value !== TextPacketType::Translation->value) {
            throw new MalformedDataException('Text payload is not the Bedrock TRANSLATION MessageAndParams variant.');
        }
        $message = $type->reader->readString(CodecSupport::MAX_SHORT_STRING_BYTES);
        if ($message->value === '') {
            throw new MalformedDataException('Translated text message cannot be empty.');
        }
        $count = $message->reader->readUnsignedVarInt();
        if ($count->value > self::MAXIMUM_PARAMETERS) {
            throw new MalformedDataException('Translated text parameter count exceeds its limit.');
        }
        $parameters = [];
        $reader = $count->reader;
        for ($index = 0; $index < $count->value; ++$index) {
            $parameter = $reader->readString(CodecSupport::MAX_SHORT_STRING_BYTES);
            $parameters[] = $parameter->value;
            $reader = $parameter->reader;
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

        return new self($message->value, $parameters, $xuid->value, $platform->value, $filtered);
    }
}
