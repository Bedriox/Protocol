<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

final readonly class UpdateAbilitiesPacket implements Packet
{
    public function __construct(public PlayerAbilities $abilities) {}

    public static function survival(int $uniqueEntityId, bool $operator = false): self
    {
        $enabled = [
            Ability::Build,
            Ability::Mine,
            Ability::DoorsAndSwitches,
            Ability::OpenContainers,
            Ability::AttackPlayers,
            Ability::AttackMobs,
        ];
        if ($operator) {
            $enabled[] = Ability::OperatorCommands;
            $enabled[] = Ability::Teleport;
        }
        return new self(new PlayerAbilities(
            $uniqueEntityId,
            $operator ? PlayerPermission::Operator : PlayerPermission::Member,
            $operator ? CommandPermissionLevel::Operator : CommandPermissionLevel::Normal,
            [AbilityLayer::fromAbilities(1, Ability::cases(), $enabled, 0.05, 1.0, 0.1)],
        ));
    }

    public function packetId(): int { return PacketIds::UPDATE_ABILITIES; }
    public function encode(): string { return $this->abilities->write(CodecSupport::writer())->toString(); }
}
