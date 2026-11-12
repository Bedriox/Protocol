<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

use Bedriox\Protocol\Exception\InvalidValueException;
use Bedriox\Protocol\Exception\MalformedDataException;

/** Bounded block-actor position and network-NBT snapshot. */
final readonly class BlockActorDataPacket implements Packet
{
    public const int MAXIMUM_NETWORK_NBT_BYTES = 16_384;

    public function __construct(public BlockPosition $position, public string $networkNbt)
    {
        if (strlen($networkNbt) > self::MAXIMUM_NETWORK_NBT_BYTES) {
            throw new InvalidValueException('Block-actor data exceeds its packet byte limit.');
        }
        NetworkNbtCompound::validate($networkNbt);
    }

    public static function fromLittleEndianNbt(BlockPosition $position, string $littleEndianNbt): self
    {
        return new self($position, LittleEndianNbtToNetwork::convert($littleEndianNbt));
    }

    public function packetId(): int { return PacketIds::BLOCK_ACTOR_DATA; }

    public function encode(): string
    {
        return CodecSupport::writer()->writeSignedVarInt($this->position->x)
            ->writeSignedVarInt($this->position->y)->writeSignedVarInt($this->position->z)
            ->writeBytes($this->networkNbt)->toString();
    }

    public static function decode(string $bytes): self
    {
        $x = CodecSupport::reader($bytes)->readSignedVarInt();
        $y = $x->reader->readSignedVarInt();
        $z = $y->reader->readSignedVarInt();
        if ($z->reader->remaining() > self::MAXIMUM_NETWORK_NBT_BYTES) {
            throw new MalformedDataException('Block-actor data exceeds its packet byte limit.');
        }
        $nbt = $z->reader->readBytes($z->reader->remaining());
        try {
            return new self(new BlockPosition($x->value, $y->value, $z->value), $nbt->value);
        } catch (InvalidValueException $e) {
            throw new MalformedDataException('Block-actor data is invalid.', previous: $e);
        }
    }
}
