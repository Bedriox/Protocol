<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

use Bedriox\Protocol\Exception\InvalidValueException;
use Bedriox\Protocol\Exception\MalformedDataException;

final readonly class CommandOutputPacket implements Packet
{
    private const int MAX_MESSAGES = 1_024;

    /** @param list<CommandOutputMessage> $messages */
    public function __construct(
        public CommandOrigin $origin,
        public CommandOutputType $type,
        public int $successCount,
        public array $messages = [],
        public ?string $data = null,
    ) {
        if ($successCount < 0 || $successCount > 0xffffffff) {
            throw new InvalidValueException('Command success count must fit an unsigned 32-bit integer.');
        }
        CodecSupport::validateCount($messages, self::MAX_MESSAGES, 'Command output messages');
        foreach ($messages as $message) {
            if (!$message instanceof CommandOutputMessage) {
                throw new InvalidValueException('Command output messages must be typed values.');
            }
        }
        if ($data !== null) {
            CodecSupport::validateString($data, CodecSupport::MAX_SHORT_STRING_BYTES, 'Command output data');
        }
    }

    public function packetId(): int { return PacketIds::COMMAND_OUTPUT; }

    public function encode(): string
    {
        $writer = CommandWireCodec::writeOrigin(CodecSupport::writer(), $this->origin)
            ->writeString($this->type->value, CodecSupport::MAX_SHORT_STRING_BYTES)
            ->writeUnsignedIntLE($this->successCount)
            ->writeUnsignedVarInt(count($this->messages));
        foreach ($this->messages as $message) {
            $writer = $writer->writeString($message->messageId, CodecSupport::MAX_SHORT_STRING_BYTES);
            $writer = CodecSupport::writeBoolean($writer, $message->internal)
                ->writeUnsignedVarInt(count($message->parameters));
            foreach ($message->parameters as $parameter) {
                $writer = $writer->writeString($parameter, CodecSupport::MAX_SHORT_STRING_BYTES);
            }
        }
        $writer = CodecSupport::writeBoolean($writer, $this->data !== null);
        if ($this->data !== null) {
            $writer = $writer->writeString($this->data, CodecSupport::MAX_SHORT_STRING_BYTES);
        }
        return $writer->toString();
    }

    public static function decode(string $bytes): self
    {
        [$origin, $reader] = CommandWireCodec::readOrigin(CodecSupport::reader($bytes));
        $typeWire = $reader->readString(CodecSupport::MAX_SHORT_STRING_BYTES);
        $type = CommandOutputType::tryFrom($typeWire->value);
        if ($type === null) {
            throw new MalformedDataException('Command output type is unknown.');
        }
        $successCount = $typeWire->reader->readUnsignedIntLE();
        $messageCount = $successCount->reader->readUnsignedVarInt();
        if ($messageCount->value > self::MAX_MESSAGES) {
            throw new MalformedDataException('Command output message count exceeds its limit.');
        }
        $reader = $messageCount->reader;
        $messages = [];
        for ($messageIndex = 0; $messageIndex < $messageCount->value; ++$messageIndex) {
            $messageId = $reader->readString(CodecSupport::MAX_SHORT_STRING_BYTES);
            [$internal, $reader] = CodecSupport::readBoolean($messageId->reader);
            $parameterCount = $reader->readUnsignedVarInt();
            if ($parameterCount->value > CommandOutputMessage::MAX_PARAMETERS) {
                throw new MalformedDataException('Command output parameter count exceeds its limit.');
            }
            $reader = $parameterCount->reader;
            $parameters = [];
            for ($parameterIndex = 0; $parameterIndex < $parameterCount->value; ++$parameterIndex) {
                $parameter = $reader->readString(CodecSupport::MAX_SHORT_STRING_BYTES);
                $parameters[] = $parameter->value;
                $reader = $parameter->reader;
            }
            $messages[] = new CommandOutputMessage($messageId->value, $internal, $parameters);
        }
        [$hasData, $reader] = CodecSupport::readBoolean($reader);
        $data = null;
        if ($hasData) {
            $readData = $reader->readString(CodecSupport::MAX_SHORT_STRING_BYTES);
            $data = $readData->value;
            $reader = $readData->reader;
        }
        CodecSupport::requireEnd($reader);
        return new self($origin, $type, $successCount->value, $messages, $data);
    }
}
