<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

/** Current bidirectional player appearance update. */
final readonly class PlayerSkinPacket implements Packet
{
    public function __construct(
        public string $uuid,
        public PlayerSkin $skin,
        public string $newSkinName,
        public string $oldSkinName,
        public bool $trustedSkin = false,
    ) {
        CodecSupport::uuidToWire($uuid);
        CodecSupport::validateString($newSkinName, CodecSupport::MAX_SHORT_STRING_BYTES, 'New skin name');
        CodecSupport::validateString($oldSkinName, CodecSupport::MAX_SHORT_STRING_BYTES, 'Old skin name');
    }

    public function packetId(): int
    {
        return PacketIds::PLAYER_SKIN;
    }

    public function encode(): string
    {
        return $this->skin->write(
            CodecSupport::writer()->writeBytes(CodecSupport::uuidToWire($this->uuid)),
            $this->trustedSkin,
        )->writeString($this->newSkinName, CodecSupport::MAX_SHORT_STRING_BYTES)
            ->writeString($this->oldSkinName, CodecSupport::MAX_SHORT_STRING_BYTES)
            ->toString();
    }

    public static function decode(string $bytes): self
    {
        $uuid = CodecSupport::reader($bytes)->readBytes(16);
        [$skin, $reader, $trusted] = PlayerSkin::read($uuid->reader);
        $newSkinName = $reader->readString(CodecSupport::MAX_SHORT_STRING_BYTES);
        $oldSkinName = $newSkinName->reader->readString(CodecSupport::MAX_SHORT_STRING_BYTES);
        CodecSupport::requireEnd($oldSkinName->reader);

        return new self(
            CodecSupport::uuidFromWire($uuid->value),
            $skin,
            $newSkinName->value,
            $oldSkinName->value,
            $trusted,
        );
    }
}
