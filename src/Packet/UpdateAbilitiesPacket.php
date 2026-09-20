<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

final readonly class UpdateAbilitiesPacket implements Packet
{
    public function __construct(public PlayerAbilities $abilities) {}

    public static function survival(int $uniqueEntityId, bool $operator = false): self
    {
        return new self(new PlayerAbilities(
            $uniqueEntityId,
            $operator ? PlayerPermission::Operator : PlayerPermission::Member,
            $operator ? CommandPermissionLevel::Operator : CommandPermissionLevel::Normal,
            [new AbilityLayer(1, 0x000fffff, $operator ? 0x000000ff : 0x0000003f, 0.05, 1.0, 0.1)],
        ));
    }

    public function packetId(): int { return PacketIds::UPDATE_ABILITIES; }
    public function encode(): string { return $this->abilities->write(CodecSupport::writer())->toString(); }
}
