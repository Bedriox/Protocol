<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

use Bedriox\Protocol\Exception\InvalidValueException;
use Bedriox\Protocol\Exception\MalformedDataException;
use Bedriox\Protocol\Value\BlockNetworkId;

/** Clientbound block-state update; runtime IDs must already be translated for protocol 2193. */
final readonly class UpdateBlockPacket implements Packet
{
    /** @param list<UpdateBlockFlag> $flags */
    public function __construct(
        public BlockPosition $position,
        public int $blockRuntimeId,
        public array $flags,
        public int $layer,
    ) {
        if ($blockRuntimeId < -0x80000000 || $blockRuntimeId > 0x7fffffff || $layer < 0 || $layer > 0xffffffff) {
            throw new InvalidValueException('Update-block runtime ID must fit signed 32-bit and layer must fit unsigned 32-bit.');
        }
        if (!array_is_list($flags)) {
            throw new InvalidValueException('Update-block flags must be a list.');
        }
        $seen = 0;
        foreach ($flags as $flag) {
            if (!$flag instanceof UpdateBlockFlag || ($seen & (1 << $flag->value)) !== 0) {
                throw new InvalidValueException('Update-block flags must be unique typed values.');
            }
            $seen |= 1 << $flag->value;
        }
    }

    public function packetId(): int { return PacketIds::UPDATE_BLOCK; }

    public function encode(): string
    {
        $flagBits = 0;
        foreach ($this->flags as $flag) {
            $flagBits |= 1 << $flag->value;
        }
        return CodecSupport::writer()->writeSignedVarInt($this->position->x)
            ->writeSignedVarInt($this->position->y)->writeSignedVarInt($this->position->z)
            ->writeUnsignedVarInt(BlockNetworkId::fromSigned($this->blockRuntimeId)->unsigned())->writeUnsignedVarInt($flagBits)
            ->writeUnsignedVarInt($this->layer)->toString();
    }

    public static function decode(string $bytes): self
    {
        $x = CodecSupport::reader($bytes)->readSignedVarInt();
        $y = $x->reader->readSignedVarInt();
        $z = $y->reader->readSignedVarInt();
        $runtimeId = $z->reader->readUnsignedVarInt();
        $flagBits = $runtimeId->reader->readUnsignedVarInt();
        if (($flagBits->value & ~0x1f) !== 0) {
            throw new MalformedDataException('Update-block flags contain unknown bits.');
        }
        $flags = [];
        foreach (UpdateBlockFlag::cases() as $flag) {
            if (($flagBits->value & (1 << $flag->value)) !== 0) {
                $flags[] = $flag;
            }
        }
        $layer = $flagBits->reader->readUnsignedVarInt();
        CodecSupport::requireEnd($layer->reader);
        return new self(new BlockPosition($x->value, $y->value, $z->value), BlockNetworkId::fromUnsigned($runtimeId->value)->signed(), $flags, $layer->value);
    }
}
