<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

use Bedriox\Protocol\Exception\MalformedDataException;

/** Protocol-2193 furnace recipe-book display preferences. */
final readonly class SetPlayerFurnaceOptionsPacket implements Packet
{
    public function __construct(public FurnaceType $type, public FurnaceOptions $options) {}

    public function packetId(): int { return PacketIds::SET_PLAYER_FURNACE_OPTIONS; }

    public function encode(): string
    {
        return CodecSupport::writeBoolean(
            CodecSupport::writer()->writeUnsignedByte($this->type->value)
                ->writeSignedVarInt($this->options->leftTab->value),
            $this->options->filtering,
        )->writeSignedVarInt($this->options->layout->value)->toString();
    }

    public static function decode(string $bytes): self
    {
        $type = CodecSupport::reader($bytes)->readUnsignedByte();
        $furnaceType = FurnaceType::tryFrom($type->value);
        if ($furnaceType === null) {
            throw new MalformedDataException('Furnace options type is unsupported.');
        }
        $tab = $type->reader->readSignedVarInt();
        $leftTab = FurnaceLeftTab::tryFrom($tab->value);
        if ($leftTab === null) {
            throw new MalformedDataException('Furnace options left tab is unsupported.');
        }
        [$filtering, $reader] = CodecSupport::readBoolean($tab->reader);
        $layout = $reader->readSignedVarInt();
        $furnaceLayout = FurnaceLayout::tryFrom($layout->value);
        if ($furnaceLayout === null) {
            throw new MalformedDataException('Furnace options layout is unsupported.');
        }
        CodecSupport::requireEnd($layout->reader);
        return new self($furnaceType, new FurnaceOptions($leftTab, $filtering, $furnaceLayout));
    }
}
