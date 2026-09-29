<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

use Bedriox\Protocol\Exception\InvalidValueException;
use Bedriox\Protocol\Exception\MalformedDataException;
use Bedriox\Protocol\Value\UnsignedLong;

/** Clientbound synchronization for one active effect on an actor. */
final readonly class MobEffectPacket implements Packet
{
    public function __construct(
        public UnsignedLong $runtimeEntityId,
        public MobEffectEvent $event,
        public MobEffectType $effect,
        public int $amplifier,
        public bool $particles,
        public int $duration,
        public UnsignedLong $tick,
        public bool $ambient,
    ) {
        foreach ([$this->amplifier, $this->duration] as $value) {
            if ($value < -0x80000000 || $value > 0x7fffffff) {
                throw new InvalidValueException('Mob-effect integer fields must fit signed 32-bit integers.');
            }
        }
    }

    public static function add(
        UnsignedLong $runtimeEntityId,
        MobEffectType $effect,
        int $amplifier,
        bool $particles,
        int $duration,
        UnsignedLong $tick,
        bool $ambient,
    ): self {
        return new self(
            $runtimeEntityId,
            MobEffectEvent::Add,
            $effect,
            $amplifier,
            $particles,
            $duration,
            $tick,
            $ambient,
        );
    }

    public static function modify(
        UnsignedLong $runtimeEntityId,
        MobEffectType $effect,
        int $amplifier,
        bool $particles,
        int $duration,
        UnsignedLong $tick,
        bool $ambient,
    ): self {
        return new self(
            $runtimeEntityId,
            MobEffectEvent::Modify,
            $effect,
            $amplifier,
            $particles,
            $duration,
            $tick,
            $ambient,
        );
    }

    public static function remove(
        UnsignedLong $runtimeEntityId,
        MobEffectType $effect,
        UnsignedLong $tick,
    ): self {
        return new self($runtimeEntityId, MobEffectEvent::Remove, $effect, 0, false, 0, $tick, false);
    }

    public function packetId(): int
    {
        return PacketIds::MOB_EFFECT;
    }

    public function encode(): string
    {
        $writer = CodecSupport::writer()
            ->writeUnsignedVarLong($this->runtimeEntityId)
            ->writeUnsignedByte($this->event->value)
            ->writeSignedVarInt($this->effect->value)
            ->writeSignedVarInt($this->amplifier);
        $writer = CodecSupport::writeBoolean($writer, $this->particles)
            ->writeSignedVarInt($this->duration)
            ->writeUnsignedVarLong($this->tick);

        return CodecSupport::writeBoolean($writer, $this->ambient)->toString();
    }

    public static function decode(string $bytes): self
    {
        $runtimeEntityId = CodecSupport::reader($bytes)->readUnsignedVarLong();
        $event = $runtimeEntityId->reader->readUnsignedByte();
        $eventType = MobEffectEvent::tryFrom($event->value);
        if ($eventType === null) {
            throw new MalformedDataException('Mob-effect event is invalid for the current protocol.');
        }
        $effectId = $event->reader->readSignedVarInt();
        $effect = MobEffectType::tryFrom($effectId->value);
        if ($effect === null) {
            throw new MalformedDataException('Mob-effect type is invalid for the current protocol.');
        }
        $amplifier = $effectId->reader->readSignedVarInt();
        [$particles, $reader] = CodecSupport::readBoolean($amplifier->reader);
        $duration = $reader->readSignedVarInt();
        $tick = $duration->reader->readUnsignedVarLong();
        [$ambient, $reader] = CodecSupport::readBoolean($tick->reader);
        CodecSupport::requireEnd($reader);

        return new self(
            $runtimeEntityId->value,
            $eventType,
            $effect,
            $amplifier->value,
            $particles,
            $duration->value,
            $tick->value,
            $ambient,
        );
    }
}
