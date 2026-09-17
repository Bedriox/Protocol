<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

use Bedriox\Protocol\Exception\InvalidValueException;
use Bedriox\Protocol\Exception\MalformedDataException;

/** Bounded inventory-layout notification; it grants no inventory mutation authority. */
final readonly class SetPlayerInventoryOptionsPacket implements Packet
{
    public function __construct(
        public int $leftTab,
        public int $rightTab,
        public bool $filtering,
        public int $layout,
        public int $craftingLayout,
    ) {
        if ($leftTab < 0 || $leftTab > 6 || $rightTab < 0 || $rightTab > 3
            || $layout < 0 || $layout > 3 || $craftingLayout < 0 || $craftingLayout > 3) {
            throw new InvalidValueException('Player inventory option is outside the current enum domain.');
        }
    }

    public function packetId(): int { return PacketIds::SET_PLAYER_INVENTORY_OPTIONS; }

    public function encode(): string
    {
        return CodecSupport::writer()->writeSignedVarInt($this->leftTab)->writeSignedVarInt($this->rightTab)
            ->writeUnsignedByte($this->filtering ? 1 : 0)->writeSignedVarInt($this->layout)
            ->writeSignedVarInt($this->craftingLayout)->toString();
    }

    public static function decode(string $bytes): self
    {
        $left = CodecSupport::reader($bytes)->readSignedVarInt();
        $right = $left->reader->readSignedVarInt();
        [$filtering, $reader] = CodecSupport::readBoolean($right->reader);
        $layout = $reader->readSignedVarInt();
        $crafting = $layout->reader->readSignedVarInt();
        CodecSupport::requireEnd($crafting->reader);
        try {
            return new self($left->value, $right->value, $filtering, $layout->value, $crafting->value);
        } catch (InvalidValueException $exception) {
            throw new MalformedDataException('Player inventory options are invalid.', previous: $exception);
        }
    }
}
