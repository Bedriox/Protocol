<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

use Bedriox\Protocol\Exception\InvalidValueException;
use Bedriox\Protocol\Exception\MalformedDataException;

final readonly class DeathInfoPacket implements Packet
{
    private const int MAXIMUM_PARAMETERS = 16;

    /** @param list<string> $parameters */
    public function __construct(public string $message, public array $parameters = [])
    {
        CodecSupport::validateString($this->message, CodecSupport::MAX_SHORT_STRING_BYTES, 'Death message');
        CodecSupport::validateCount($this->parameters, self::MAXIMUM_PARAMETERS, 'Death message parameters');
        foreach ($this->parameters as $parameter) {
            if (!is_string($parameter)) {
                throw new InvalidValueException('Death message parameters must be strings.');
            }
            CodecSupport::validateString($parameter, CodecSupport::MAX_SHORT_STRING_BYTES, 'Death message parameter');
        }
    }

    public function packetId(): int { return PacketIds::DEATH_INFO; }

    public function encode(): string
    {
        $writer = CodecSupport::writer()->writeString($this->message, CodecSupport::MAX_SHORT_STRING_BYTES)
            ->writeUnsignedVarInt(count($this->parameters));
        foreach ($this->parameters as $parameter) {
            $writer = $writer->writeString($parameter, CodecSupport::MAX_SHORT_STRING_BYTES);
        }

        return $writer->toString();
    }

    public static function decode(string $bytes): self
    {
        $message = CodecSupport::reader($bytes)->readString(CodecSupport::MAX_SHORT_STRING_BYTES);
        $count = $message->reader->readUnsignedVarInt();
        if ($count->value > self::MAXIMUM_PARAMETERS) {
            throw new MalformedDataException('Death message parameter count exceeds its limit.');
        }
        $parameters = [];
        $reader = $count->reader;
        for ($index = 0; $index < $count->value; ++$index) {
            $parameter = $reader->readString(CodecSupport::MAX_SHORT_STRING_BYTES);
            $parameters[] = $parameter->value;
            $reader = $parameter->reader;
        }
        CodecSupport::requireEnd($reader);

        return new self($message->value, $parameters);
    }
}
