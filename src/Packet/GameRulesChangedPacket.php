<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

final readonly class GameRulesChangedPacket implements Packet
{
    public function __construct(public GameRuleSet $rules = new GameRuleSet([])) {}

    public static function survivalDefaults(): self
    {
        return new self(GameRuleSet::survivalDefaults());
    }

    public function packetId(): int { return PacketIds::GAME_RULES_CHANGED; }
    public function encode(): string { return $this->rules->encode(); }
}
