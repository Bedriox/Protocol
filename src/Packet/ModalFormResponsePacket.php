<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

use Bedriox\Protocol\Exception\InvalidValueException;

/** Returns a submitted form value or the reason the client closed it. */
final readonly class ModalFormResponsePacket implements Packet
{
    public const int MAXIMUM_RESPONSE_BYTES = 32_768;

    public ?int $cancelReason;

    public function __construct(
        public int $formId,
        public ?string $responseJson,
        ModalFormCancelReason|int|null $cancelReason,
    ) {
        if ($formId < 0 || $formId > 0xffffffff) {
            throw new InvalidValueException('Form ID must fit in an unsigned 32-bit integer.');
        }
        if ($responseJson !== null) {
            CodecSupport::validateString($responseJson, self::MAXIMUM_RESPONSE_BYTES, 'Form response');
        }
        $this->cancelReason = $cancelReason instanceof ModalFormCancelReason ? $cancelReason->value : $cancelReason;
        if ($this->cancelReason !== null && ($this->cancelReason < 0 || $this->cancelReason > 0xff)) {
            throw new InvalidValueException('Form cancel reason must fit in one byte.');
        }
    }

    public function packetId(): int { return PacketIds::MODAL_FORM_RESPONSE; }

    public function encode(): string
    {
        $writer = CodecSupport::writer()->writeUnsignedVarInt($this->formId);
        $writer = CodecSupport::writeBoolean($writer, $this->responseJson !== null);
        if ($this->responseJson !== null) {
            $writer = $writer->writeString($this->responseJson, self::MAXIMUM_RESPONSE_BYTES);
        }
        $writer = CodecSupport::writeBoolean($writer, $this->cancelReason !== null);
        if ($this->cancelReason !== null) {
            $writer = $writer->writeUnsignedByte($this->cancelReason);
        }
        return $writer->toString();
    }

    public static function decode(string $bytes): self
    {
        $formId = CodecSupport::reader($bytes)->readUnsignedVarInt();
        [$hasResponse, $reader] = CodecSupport::readBoolean($formId->reader);
        $responseJson = null;
        if ($hasResponse) {
            $response = $reader->readString(self::MAXIMUM_RESPONSE_BYTES);
            CodecSupport::validateWireString($response->value, self::MAXIMUM_RESPONSE_BYTES, 'Form response');
            $responseJson = $response->value;
            $reader = $response->reader;
        }

        [$hasCancelReason, $reader] = CodecSupport::readBoolean($reader);
        $cancelReason = null;
        if ($hasCancelReason) {
            $reason = $reader->readUnsignedByte();
            $cancelReason = $reason->value;
            $reader = $reason->reader;
        }
        CodecSupport::requireEnd($reader);

        return new self($formId->value, $responseJson, $cancelReason);
    }
}
