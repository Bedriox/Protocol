<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

use Bedriox\Protocol\Exception\InvalidValueException;

/** Clientbound level event with opaque event-specific data. */
final readonly class LevelEventPacket implements Packet
{
    private const int PARTICLE_EVENT_MASK = 0x4000;

    public function __construct(
        public int $eventId,
        public LevelEventPosition $position,
        public int $data,
    ) {
        foreach ([$eventId, $data] as $value) {
            if ($value < -0x80000000 || $value > 0x7fffffff) {
                throw new InvalidValueException('Level-event identifiers and data must fit signed 32-bit integers.');
            }
        }
    }

    public function packetId(): int { return PacketIds::LEVEL_EVENT; }

    public function type(): ?LevelEventType
    {
        return LevelEventType::tryFrom($this->eventId);
    }

    public static function startBlockBreak(LevelEventPosition $position, int $progress): self
    {
        return new self(LevelEventType::StartBlockBreak->value, $position, $progress);
    }

    public static function stopBlockBreak(LevelEventPosition $position): self
    {
        return new self(LevelEventType::StopBlockBreak->value, $position, 0);
    }

    public static function updateBlockBreak(LevelEventPosition $position, int $progress): self
    {
        return new self(LevelEventType::UpdateBlockBreak->value, $position, $progress);
    }

    public static function destroyBlock(LevelEventPosition $position, int $blockRuntimeId, bool $sound = true): self
    {
        return new self(
            ($sound ? LevelEventType::DestroyBlock : LevelEventType::DestroyBlockWithoutSound)->value,
            $position,
            $blockRuntimeId,
        );
    }

    public static function crackBlock(LevelEventPosition $position, int $blockRuntimeId): self
    {
        return new self(LevelEventType::CrackBlock->value, $position, $blockRuntimeId);
    }

    public static function punchBlock(LevelEventPosition $position, int $blockRuntimeId, int $face): self
    {
        $event = match ($face) {
            0 => LevelEventType::PunchBlockDown,
            1 => LevelEventType::PunchBlockUp,
            2 => LevelEventType::PunchBlockNorth,
            3 => LevelEventType::PunchBlockSouth,
            4 => LevelEventType::PunchBlockWest,
            5 => LevelEventType::PunchBlockEast,
            default => throw new InvalidValueException('Block face is outside the protocol range.'),
        };
        return new self($event->value, $position, $blockRuntimeId);
    }

    public static function punchBlockFace(
        LevelEventPosition $position,
        int $blockRuntimeId,
        LevelEventBlockFace $face,
    ): self {
        return self::punchBlock($position, $blockRuntimeId, $face->value);
    }

    public static function particle(LevelEventPosition $position, LevelEventParticleType $type): self
    {
        return self::particleWithData($position, $type, 0);
    }

    public static function scalarParticle(
        LevelEventPosition $position,
        LevelEventParticleType $type,
        int $value,
    ): self {
        if (!in_array($type, [
            LevelEventParticleType::BlockForceField,
            LevelEventParticleType::Critical,
            LevelEventParticleType::Heart,
            LevelEventParticleType::Ink,
            LevelEventParticleType::Redstone,
            LevelEventParticleType::Smoke,
        ], true) || $value < 0 || $value > 65_535) {
            throw new InvalidValueException('Particle type or scalar value is invalid.');
        }

        return self::particleWithData($position, $type, $value);
    }

    public static function coloredParticle(
        LevelEventPosition $position,
        LevelEventParticleType $type,
        LevelEventParticleColor $color,
    ): self {
        if (!in_array($type, [
            LevelEventParticleType::FallingDust,
            LevelEventParticleType::MobSpell,
            LevelEventParticleType::MobSpellAmbient,
            LevelEventParticleType::MobSpellInstantaneous,
        ], true)) {
            throw new InvalidValueException('Particle type does not carry color data.');
        }

        return self::particleWithData($position, $type, $color->signedArgb());
    }

    public static function terrainParticle(LevelEventPosition $position, int $blockRuntimeId): self
    {
        return self::particleWithData($position, LevelEventParticleType::Terrain, $blockRuntimeId);
    }

    public static function itemBreakParticle(LevelEventPosition $position, int $itemRuntimeId, int $auxValue): self
    {
        if ($itemRuntimeId < 0 || $itemRuntimeId > 0xffff || $auxValue < 0 || $auxValue > 0xffff) {
            throw new InvalidValueException('Particle item runtime ID and auxiliary value must fit unsigned 16-bit integers.');
        }
        $data = ($itemRuntimeId << 16) | $auxValue;

        return self::particleWithData(
            $position,
            LevelEventParticleType::ItemBreak,
            $data > 0x7fffffff ? $data - 0x100000000 : $data,
        );
    }

    public static function potionSplashParticle(LevelEventPosition $position, LevelEventParticleColor $color): self
    {
        return new self(LevelEventType::PotionSplash->value, $position, $color->signedArgb());
    }

    public static function dragonEggTeleportParticle(
        LevelEventPosition $position,
        int $offsetX,
        int $offsetY,
        int $offsetZ,
    ): self {
        foreach ([$offsetX, $offsetY, $offsetZ] as $offset) {
            if ($offset < -255 || $offset > 255) {
                throw new InvalidValueException('Dragon-egg particle offsets must be between -255 and 255.');
            }
        }
        $data = ($offsetZ < 0 ? 1 << 26 : 0)
            | ($offsetY < 0 ? 1 << 25 : 0)
            | ($offsetX < 0 ? 1 << 24 : 0)
            | (abs($offsetX) << 16)
            | (abs($offsetY) << 8)
            | abs($offsetZ);

        return new self(LevelEventType::DragonEggTeleport->value, $position, $data);
    }

    public static function mobSpawnParticle(LevelEventPosition $position, int $width, int $height): self
    {
        if ($width < 0 || $width > 255 || $height < 0 || $height > 255) {
            throw new InvalidValueException('Mob-spawn particle dimensions must be between 0 and 255.');
        }

        return new self(LevelEventType::MobSpawn->value, $position, $width | ($height << 8));
    }

    public static function endermanTeleportParticle(LevelEventPosition $position): self
    {
        return new self(LevelEventType::EndermanTeleport->value, $position, 0);
    }

    private static function particleWithData(
        LevelEventPosition $position,
        LevelEventParticleType $type,
        int $data,
    ): self {
        return new self(self::PARTICLE_EVENT_MASK | $type->value, $position, $data);
    }

    public function encode(): string
    {
        return CodecSupport::writer()->writeSignedVarInt($this->eventId)
            ->writeFloatLE($this->position->x)->writeFloatLE($this->position->y)->writeFloatLE($this->position->z)
            ->writeSignedVarInt($this->data)->toString();
    }

    public static function decode(string $bytes): self
    {
        $eventId = CodecSupport::reader($bytes)->readSignedVarInt();
        $x = $eventId->reader->readFloatLE();
        $y = $x->reader->readFloatLE();
        $z = $y->reader->readFloatLE();
        foreach ([$x->value, $y->value, $z->value] as $coordinate) {
            CodecSupport::validateFiniteFloat($coordinate, 'Level-event position', true);
        }
        $data = $z->reader->readSignedVarInt();
        CodecSupport::requireEnd($data->reader);
        return new self($eventId->value, new LevelEventPosition($x->value, $y->value, $z->value), $data->value);
    }
}
