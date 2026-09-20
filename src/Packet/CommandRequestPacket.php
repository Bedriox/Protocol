<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

final readonly class CommandRequestPacket implements Packet
{
    private const string CURRENT_VERSION_TOKEN = 'latest';

    public function __construct(
        public string $command,
        public CommandOrigin $origin,
        public bool $internal = false,
        public string $version = self::CURRENT_VERSION_TOKEN,
    ) {
        CodecSupport::validateString($command, CodecSupport::MAX_SHORT_STRING_BYTES, 'Command request');
        CodecSupport::validateString($version, CodecSupport::MAX_SHORT_STRING_BYTES, 'Command version');
    }

    public function packetId(): int { return PacketIds::COMMAND_REQUEST; }

    public function encode(): string
    {
        $writer = CodecSupport::writer()->writeString($this->command, CodecSupport::MAX_SHORT_STRING_BYTES);
        $writer = CommandWireCodec::writeOrigin($writer, $this->origin);
        return CodecSupport::writeBoolean($writer, $this->internal)
            ->writeString($this->version, CodecSupport::MAX_SHORT_STRING_BYTES)->toString();
    }

    public static function decode(string $bytes): self
    {
        $command = CodecSupport::reader($bytes)->readString(CodecSupport::MAX_SHORT_STRING_BYTES);
        [$origin, $reader] = CommandWireCodec::readOrigin($command->reader);
        [$internal, $reader] = CodecSupport::readBoolean($reader);
        $version = $reader->readString(CodecSupport::MAX_SHORT_STRING_BYTES);
        CodecSupport::requireEnd($version->reader);
        return new self($command->value, $origin, $internal, $version->value);
    }
}
