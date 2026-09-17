<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

final readonly class UpdateAdventureSettingsPacket implements Packet
{
    public function __construct(
        public bool $noPlayerVersusMob = false,
        public bool $noMobVersusPlayer = false,
        public bool $immutableWorld = false,
        public bool $showNameTags = false,
        public bool $autoJump = true,
    ) {}

    public function packetId(): int { return PacketIds::UPDATE_ADVENTURE_SETTINGS; }
    public function encode(): string
    {
        $writer = CodecSupport::writer();
        foreach ([$this->noPlayerVersusMob, $this->noMobVersusPlayer, $this->immutableWorld, $this->showNameTags, $this->autoJump] as $flag) {
            $writer = CodecSupport::writeBoolean($writer, $flag);
        }
        return $writer->toString();
    }
}
