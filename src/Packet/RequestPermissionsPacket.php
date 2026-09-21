<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

use Bedriox\Protocol\Exception\InvalidValueException;
use Bedriox\Protocol\Exception\MalformedDataException;

final readonly class RequestPermissionsPacket implements Packet
{
    public function __construct(
        public int $actorUniqueId,
        public PlayerPermission $permission,
        public int $customPermissions,
    ) {
        if ($customPermissions < 0 || $customPermissions > 0xffff) {
            throw new InvalidValueException('Custom permission bits exceed the unsigned-short range.');
        }
    }

    public function packetId(): int { return PacketIds::REQUEST_PERMISSIONS; }

    public function encode(): string
    {
        return CodecSupport::writer()->writeSignedLongLE($this->actorUniqueId)
            ->writeSignedVarInt($this->permission->value)
            ->writeUnsignedShortLE($this->customPermissions)->toString();
    }

    public static function decode(string $bytes): self
    {
        $actorUniqueId = CodecSupport::reader($bytes)->readSignedLongLE();
        $permission = $actorUniqueId->reader->readSignedVarInt();
        $typed = PlayerPermission::tryFrom($permission->value);
        if ($typed === null) {
            throw new MalformedDataException('Requested player permission is unknown.');
        }
        $custom = $permission->reader->readUnsignedShortLE();
        CodecSupport::requireEnd($custom->reader);
        return new self($actorUniqueId->value, $typed, $custom->value);
    }
}
