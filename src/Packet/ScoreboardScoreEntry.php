<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

use Bedriox\Protocol\Exception\InvalidValueException;

/** One immutable entry in the current SetScore packet. */
final readonly class ScoreboardScoreEntry
{
    private function __construct(
        public ScoreboardEntryType $type,
        public int $scoreboardId,
        public ?string $objectiveId,
        public int $score,
        public ?int $actorUniqueId,
        public ?string $name,
    ) {
        if ($this->objectiveId !== null) {
            CodecSupport::validateString($this->objectiveId, CodecSupport::MAX_SHORT_STRING_BYTES, 'Scoreboard objective ID');
        }
        if ($this->name !== null) {
            CodecSupport::validateString($this->name, CodecSupport::MAX_SHORT_STRING_BYTES, 'Scoreboard entry name');
        }
        if ($this->score < -0x80000000 || $this->score > 0x7fffffff) {
            throw new InvalidValueException('Scoreboard score must fit in a signed 32-bit integer.');
        }

        if ($this->type === ScoreboardEntryType::REMOVE) {
            if ($this->score !== 0 || $this->actorUniqueId !== null || $this->name !== null) {
                throw new InvalidValueException('Removed scoreboard entries cannot carry score-holder fields.');
            }
            return;
        }

        if ($this->objectiveId === null || $this->objectiveId === '') {
            throw new InvalidValueException('Changed scoreboard entries require a non-empty objective ID.');
        }
        if ($this->type === ScoreboardEntryType::FAKE_PLAYER) {
            if ($this->name === null || $this->name === '' || $this->actorUniqueId !== null) {
                throw new InvalidValueException('Fake-player scoreboard entries require only a non-empty name.');
            }
            return;
        }
        if ($this->actorUniqueId === null || $this->name !== null) {
            throw new InvalidValueException('Player and entity scoreboard entries require only an actor unique ID.');
        }
    }

    public static function remove(int $scoreboardId, ?string $objectiveId = null): self
    {
        return new self(ScoreboardEntryType::REMOVE, $scoreboardId, $objectiveId, 0, null, null);
    }

    public static function player(int $scoreboardId, string $objectiveId, int $score, int $playerUniqueId): self
    {
        return new self(ScoreboardEntryType::PLAYER, $scoreboardId, $objectiveId, $score, $playerUniqueId, null);
    }

    public static function entity(int $scoreboardId, string $objectiveId, int $score, int $actorUniqueId): self
    {
        return new self(ScoreboardEntryType::ENTITY, $scoreboardId, $objectiveId, $score, $actorUniqueId, null);
    }

    public static function fakePlayer(int $scoreboardId, string $objectiveId, int $score, string $name): self
    {
        return new self(ScoreboardEntryType::FAKE_PLAYER, $scoreboardId, $objectiveId, $score, null, $name);
    }

    public function changedObjectiveId(): string
    {
        if ($this->objectiveId === null || $this->type === ScoreboardEntryType::REMOVE) {
            throw new \LogicException('Removed scoreboard entries do not have a required objective ID.');
        }
        return $this->objectiveId;
    }

    public function requiredActorUniqueId(): int
    {
        if ($this->actorUniqueId === null) {
            throw new \LogicException('This scoreboard entry does not identify an actor.');
        }
        return $this->actorUniqueId;
    }

    public function requiredName(): string
    {
        if ($this->name === null) {
            throw new \LogicException('This scoreboard entry does not identify a fake player.');
        }
        return $this->name;
    }
}
