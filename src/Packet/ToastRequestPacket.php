<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

/** Clientbound toast notification title and body. */
final readonly class ToastRequestPacket implements Packet
{
    public function __construct(
        public string $title,
        public string $body,
    ) {
        CodecSupport::validateString($title, CodecSupport::MAX_SHORT_STRING_BYTES, 'Toast title');
        CodecSupport::validateString($body, CodecSupport::MAX_SHORT_STRING_BYTES, 'Toast body');
    }

    public function packetId(): int
    {
        return PacketIds::TOAST_REQUEST;
    }

    public function encode(): string
    {
        return CodecSupport::writer()
            ->writeString($this->title, CodecSupport::MAX_SHORT_STRING_BYTES)
            ->writeString($this->body, CodecSupport::MAX_SHORT_STRING_BYTES)
            ->toString();
    }

    public static function decode(string $bytes): self
    {
        $title = CodecSupport::reader($bytes)->readString(CodecSupport::MAX_SHORT_STRING_BYTES);
        $body = $title->reader->readString(CodecSupport::MAX_SHORT_STRING_BYTES);
        CodecSupport::requireEnd($body->reader);

        return new self($title->value, $body->value);
    }
}
