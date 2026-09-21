<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

use Bedriox\Protocol\Exception\InvalidValueException;
use Bedriox\Protocol\ProtocolVersion;
use Bedriox\Protocol\Value\UnsignedLong;

/** Bounded StartGame payload with a factory for Bedriox's Bedrock fixed-flat profile. */
final readonly class StartGamePacket implements Packet
{
    private const int MAX_BYTES = 1_048_576;
    private const int MAX_BLOCK_PROPERTIES = 1_024;
    private const int MAX_REWIND_HISTORY_SIZE = 0x7fffffff;

    private function __construct(
        private string $payload,
        public GameType $playerGameType,
        public GameType $levelGameType,
    )
    {
        if ($payload === '' || strlen($payload) > self::MAX_BYTES) { throw new InvalidValueException('StartGame payload is empty or oversized.'); }
    }

    /**
     * @param list<BlockPropertyData> $blockProperties
     * @param ?list<ExperimentData> $experiments
     */
    public static function fixedFlat(
        int $uniqueEntityId,
        UnsignedLong $runtimeEntityId,
        float $x,
        float $y,
        float $z,
        string $levelId,
        string $levelName,
        int $currentTick = 0,
        ?GameRuleSet $gameRules = null,
        string $gameVersion = ProtocolVersion::GAME_VERSION,
        array $blockProperties = [],
        ?array $experiments = null,
        int $worldSeed = 0,
        int $worldSpawnX = 0,
        int $worldSpawnY = 64,
        int $worldSpawnZ = 0,
        float $playerPitch = 0.0,
        float $playerYaw = 0.0,
        int $rewindHistorySize = 40,
        GameType $playerGameType = GameType::Survival,
        GameType $levelGameType = GameType::Survival,
    ): self
    {
        foreach ([$x, $y, $z] as $value) { CodecSupport::validateFiniteFloat($value, 'StartGame position'); }
        foreach ([$playerPitch, $playerYaw] as $value) { CodecSupport::validateFiniteFloat($value, 'StartGame rotation'); }
        foreach ([$worldSpawnX, $worldSpawnY, $worldSpawnZ] as $coordinate) {
            if ($coordinate < -0x80000000 || $coordinate > 0x7fffffff) {
                throw new InvalidValueException('StartGame world-spawn coordinate must fit a signed 32-bit integer.');
            }
        }
        if ($rewindHistorySize < 0 || $rewindHistorySize > self::MAX_REWIND_HISTORY_SIZE) {
            throw new InvalidValueException('StartGame rewind-history size must fit a non-negative signed 32-bit integer.');
        }
        CodecSupport::validateString($levelId, CodecSupport::MAX_SHORT_STRING_BYTES, 'Level ID');
        CodecSupport::validateString($levelName, CodecSupport::MAX_SHORT_STRING_BYTES, 'Level name');
        CodecSupport::validateString($gameVersion, 16, 'Game version');
        CodecSupport::validateCount($blockProperties, self::MAX_BLOCK_PROPERTIES, 'Block properties');
        $experiments ??= ExperimentData::requiredForDataDrivenBlocks();
        CodecSupport::validateCount($experiments, CodecSupport::MAX_EXPERIMENTS, 'Experiments');
        $blockNames = [];
        foreach ($blockProperties as $blockProperty) {
            if (!$blockProperty instanceof BlockPropertyData || isset($blockNames[$blockProperty->name])) {
                throw new InvalidValueException('Block properties must contain unique BlockPropertyData entries.');
            }
            $blockNames[$blockProperty->name] = true;
        }
        $experimentNames = [];
        foreach ($experiments as $experiment) {
            if (!$experiment instanceof ExperimentData || isset($experimentNames[$experiment->name])) {
                throw new InvalidValueException('Experiments must contain unique ExperimentData entries.');
            }
            $experimentNames[$experiment->name] = true;
        }
        $w = CodecSupport::writer()->writeSignedVarLong($uniqueEntityId)->writeUnsignedVarLong($runtimeEntityId)->writeSignedVarInt($playerGameType->value)
            ->writeFloatLE($x)->writeFloatLE($y)->writeFloatLE($z)->writeFloatLE($playerPitch)->writeFloatLE($playerYaw)
            ->writeSignedLongLE($worldSeed)->writeUnsignedShortLE(0)->writeString('plains', 16)
            ->writeSignedVarInt(0)->writeSignedVarInt(1)->writeSignedVarInt($levelGameType->value);
        $w = CodecSupport::writeBoolean($w, false)->writeSignedVarInt(0)->writeSignedVarInt($worldSpawnX)
            ->writeSignedVarInt($worldSpawnY)->writeSignedVarInt($worldSpawnZ);
        foreach ([true, false, false] as $flag) { $w = CodecSupport::writeBoolean($w, $flag); }
        $w = CodecSupport::writeBoolean($w, false); // not exported from the editor
        $w = $w->writeSignedVarInt(-1)->writeSignedVarInt(0);
        $w = CodecSupport::writeBoolean($w, false)->writeString('', 0)->writeFloatLE(0.0)->writeFloatLE(0.0);
        foreach ([false, true, true] as $flag) { $w = CodecSupport::writeBoolean($w, $flag); }
        $w = $w->writeSignedVarInt(4)->writeSignedVarInt(4);
        // Commands stay disabled until the server can follow this packet with a matching
        // AvailableCommands registry. Advertising commands without that registry leaves the
        // retail client with an incomplete world bootstrap.
        $w = CodecSupport::writeBoolean($w, false);
        $w = CodecSupport::writeBoolean($w, false)
            ->writeBytes(($gameRules ?? GameRuleSet::survivalDefaults())->encode())->writeSignedIntLE(count($experiments));
        foreach ($experiments as $experiment) {
            $w = $w->writeString($experiment->name, 256);
            $w = CodecSupport::writeBoolean($w, $experiment->enabled);
        }
        $w = CodecSupport::writeBoolean($w, $experiments !== []);
        foreach ([false, false] as $flag) { $w = CodecSupport::writeBoolean($w, $flag); }
        $w = $w->writeUnsignedByte(1)->writeSignedIntLE(4);
        foreach (array_fill(0, 10, false) as $flag) { $w = CodecSupport::writeBoolean($w, $flag); }
        $w = $w->writeString($gameVersion, 16)->writeSignedIntLE(16)->writeSignedIntLE(16);
        $w = CodecSupport::writeBoolean($w, false)->writeString('', 0)->writeString('', 0);
        $w = CodecSupport::writeBoolean($w, false)->writeUnsignedByte(0); $w = CodecSupport::writeBoolean($w, false);
        $w = $w->writeSignedVarInt(0); $w = CodecSupport::writeBoolean($w, false);
        $w = $w->writeString($levelId, CodecSupport::MAX_SHORT_STRING_BYTES)->writeString($levelName, CodecSupport::MAX_SHORT_STRING_BYTES)->writeString('', 0);
        $w = CodecSupport::writeBoolean($w, false)->writeSignedVarInt($rewindHistorySize); $w = CodecSupport::writeBoolean($w, true)
            ->writeSignedLongLE($currentTick)->writeSignedVarInt(0)->writeUnsignedVarInt(count($blockProperties));
        foreach ($blockProperties as $blockProperty) {
            $w = $w->writeString($blockProperty->name, 256)->writeBytes($blockProperty->networkNbt);
        }
        $w = $w->writeString('', 0);
        // Required for the authoritative ItemStackRequest inventory conversation.
        // Advertising false makes retail clients fall back to legacy prediction-only
        // InventoryTransaction actions for ordinary inventory moves and splits.
        $w = CodecSupport::writeBoolean($w, true)->writeString('', 0)
            ->writeBytes("\x0a\x00\x00")->writeSignedLongLE(0)->writeBytes(str_repeat("\0", 16));
        foreach ([false, false, true, false] as $flag) { $w = CodecSupport::writeBoolean($w, $flag); }
        foreach (array_fill(0, 4, '') as $id) { $w = $w->writeString($id, 0); }
        return new self($w->toString(), $playerGameType, $levelGameType);
    }

    public function packetId(): int { return PacketIds::START_GAME; }
    public function encode(): string { return $this->payload; }
}
