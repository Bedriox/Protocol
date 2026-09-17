<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

final readonly class UpdateAbilitiesPacket implements Packet
{
    public function __construct(public PlayerAbilities $abilities) {}

    public static function survival(int $uniqueEntityId): self
    {
        return new self(new PlayerAbilities($uniqueEntityId, 1, 0, [
            new AbilityLayer(1, 0x000fffff, 0x0000003f, 0.05, 1.0, 0.1),
        ]));
    }

    public function packetId(): int { return PacketIds::UPDATE_ABILITIES; }
    public function encode(): string { return $this->abilities->write(CodecSupport::writer())->toString(); }
}
