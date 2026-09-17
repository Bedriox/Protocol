<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

use Bedriox\Protocol\Exception\InvalidValueException;
use Bedriox\Protocol\Exception\MalformedDataException;

final readonly class DisconnectPacket implements Packet
{
    public function __construct(
        public int $reason,
        public bool $messageSkipped,
        public string $kickMessage = '',
        public string $filteredMessage = '',
    ) {
        if ($reason < DisconnectReason::MIN || $reason > DisconnectReason::MAX) {
            throw new InvalidValueException('Disconnect reason is not defined by the supported Bedrock protocol.');
        }
        CodecSupport::validateString($kickMessage, CodecSupport::MAX_SHORT_STRING_BYTES, 'Kick message');
        CodecSupport::validateString($filteredMessage, CodecSupport::MAX_SHORT_STRING_BYTES, 'Filtered message');
        if ($messageSkipped && ($kickMessage !== '' || $filteredMessage !== '')) {
            throw new InvalidValueException('Skipped disconnect messages must be empty.');
        }
    }

    public function packetId(): int
    {
        return PacketIds::DISCONNECT;
    }

    public function encode(): string
    {
        $writer = CodecSupport::writer()->writeSignedVarInt($this->reason)
            ->writeUnsignedVarInt($this->messageSkipped ? 1 : 0);
        if (!$this->messageSkipped) {
            $writer = $writer->writeString($this->kickMessage, CodecSupport::MAX_SHORT_STRING_BYTES)
                ->writeString($this->filteredMessage, CodecSupport::MAX_SHORT_STRING_BYTES);
        }
        return $writer->toString();
    }

    public static function decode(string $bytes): self
    {
        $reason = CodecSupport::reader($bytes)->readSignedVarInt();
        if ($reason->value < DisconnectReason::MIN || $reason->value > DisconnectReason::MAX) {
            throw new MalformedDataException('Disconnect reason is not defined by the supported Bedrock protocol.');
        }
        $variant = $reason->reader->readUnsignedVarInt();
        if ($variant->value > 1) {
            throw new MalformedDataException('Disconnect message variant must be 0 or 1.');
        }
        if ($variant->value === 1) {
            CodecSupport::requireEnd($variant->reader);
            return new self($reason->value, true);
        }
        $kick = $variant->reader->readString(CodecSupport::MAX_SHORT_STRING_BYTES);
        $filtered = $kick->reader->readString(CodecSupport::MAX_SHORT_STRING_BYTES);
        CodecSupport::requireEnd($filtered->reader);
        return new self($reason->value, false, $kick->value, $filtered->value);
    }
}
