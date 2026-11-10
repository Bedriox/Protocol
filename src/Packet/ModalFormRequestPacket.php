<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

use Bedriox\Protocol\Exception\InvalidValueException;

/** Sends a JSON server form definition to the client. */
final readonly class ModalFormRequestPacket implements Packet
{
    public const int MAXIMUM_DEFINITION_BYTES = 262_144;

    public function __construct(
        public int $formId,
        public string $definitionJson,
    ) {
        if ($formId < 0 || $formId > 0xffffffff) {
            throw new InvalidValueException('Form ID must fit in an unsigned 32-bit integer.');
        }
        CodecSupport::validateString($definitionJson, self::MAXIMUM_DEFINITION_BYTES, 'Form definition');
    }

    public function packetId(): int { return PacketIds::MODAL_FORM_REQUEST; }

    public function encode(): string
    {
        return CodecSupport::writer()
            ->writeUnsignedVarInt($this->formId)
            ->writeString($this->definitionJson, self::MAXIMUM_DEFINITION_BYTES)
            ->toString();
    }

    public static function decode(string $bytes): self
    {
        $formId = CodecSupport::reader($bytes)->readUnsignedVarInt();
        $definition = $formId->reader->readString(self::MAXIMUM_DEFINITION_BYTES);
        CodecSupport::requireEnd($definition->reader);
        CodecSupport::validateWireString($definition->value, self::MAXIMUM_DEFINITION_BYTES, 'Form definition');

        return new self($formId->value, $definition->value);
    }
}
