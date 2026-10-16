<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

use Bedriox\Protocol\Exception\InvalidValueException;
use Bedriox\Protocol\Exception\MalformedDataException;

final readonly class SetScorePacket implements Packet
{
    private const int MAXIMUM_ENTRIES = 1_024;

    /** @param list<ScoreboardScoreEntry> $entries */
    public function __construct(public array $entries)
    {
        CodecSupport::validateCount($this->entries, self::MAXIMUM_ENTRIES, 'Scoreboard score entries');
        foreach ($this->entries as $entry) {
            if (!$entry instanceof ScoreboardScoreEntry) {
                throw new InvalidValueException('Scoreboard score entries must be typed entry values.');
            }
        }
    }

    public function packetId(): int { return PacketIds::SET_SCORE; }

    public function encode(): string
    {
        $writer = CodecSupport::writer()->writeUnsignedVarInt(count($this->entries));
        foreach ($this->entries as $entry) {
            $writer = $writer
                ->writeUnsignedVarInt($entry->type->value)
                ->writeString($entry->type->wireName(), CodecSupport::MAX_SHORT_STRING_BYTES)
                ->writeSignedVarLong($entry->scoreboardId);
            if ($entry->type === ScoreboardEntryType::REMOVE) {
                $hasObjective = $entry->objectiveId !== null;
                $writer = CodecSupport::writeBoolean($writer, $hasObjective);
                if ($hasObjective) {
                    $writer = $writer->writeString($entry->objectiveId, CodecSupport::MAX_SHORT_STRING_BYTES);
                }
                continue;
            }

            $writer = $writer
                ->writeString($entry->changedObjectiveId(), CodecSupport::MAX_SHORT_STRING_BYTES)
                ->writeSignedIntLE($entry->score);
            $writer = $entry->type === ScoreboardEntryType::FAKE_PLAYER
                ? $writer->writeString($entry->requiredName(), CodecSupport::MAX_SHORT_STRING_BYTES)
                : $writer->writeSignedVarLong($entry->requiredActorUniqueId());
        }
        return $writer->toString();
    }

    public static function decode(string $bytes): self
    {
        $count = CodecSupport::reader($bytes)->readUnsignedVarInt();
        if ($count->value > self::MAXIMUM_ENTRIES) {
            throw new MalformedDataException('Scoreboard score entry count exceeds its limit.');
        }

        $entries = [];
        $reader = $count->reader;
        for ($index = 0; $index < $count->value; ++$index) {
            $typeWire = $reader->readUnsignedVarInt();
            $type = ScoreboardEntryType::tryFrom($typeWire->value);
            if ($type === null) {
                throw new MalformedDataException('Unknown scoreboard score entry type.');
            }
            $typeName = $typeWire->reader->readString(CodecSupport::MAX_SHORT_STRING_BYTES);
            if ($typeName->value !== $type->wireName()) {
                throw new MalformedDataException('Scoreboard score entry type name does not match its ordinal.');
            }
            $scoreboardId = $typeName->reader->readSignedVarLong();
            if ($type === ScoreboardEntryType::REMOVE) {
                [$hasObjective, $reader] = CodecSupport::readBoolean($scoreboardId->reader);
                $objectiveId = null;
                if ($hasObjective) {
                    $objective = $reader->readString(CodecSupport::MAX_SHORT_STRING_BYTES);
                    $objectiveId = $objective->value;
                    $reader = $objective->reader;
                }
                $entries[] = ScoreboardScoreEntry::remove($scoreboardId->value, $objectiveId);
                continue;
            }

            $objectiveId = $scoreboardId->reader->readString(CodecSupport::MAX_SHORT_STRING_BYTES);
            $score = $objectiveId->reader->readSignedIntLE();
            if ($objectiveId->value === '') {
                throw new MalformedDataException('Changed scoreboard entries require a non-empty objective ID.');
            }
            if ($type === ScoreboardEntryType::FAKE_PLAYER) {
                $name = $score->reader->readString(CodecSupport::MAX_SHORT_STRING_BYTES);
                if ($name->value === '') {
                    throw new MalformedDataException('Fake-player scoreboard entries require a non-empty name.');
                }
                $entries[] = ScoreboardScoreEntry::fakePlayer($scoreboardId->value, $objectiveId->value, $score->value, $name->value);
                $reader = $name->reader;
                continue;
            }

            $actorId = $score->reader->readSignedVarLong();
            $entries[] = $type === ScoreboardEntryType::PLAYER
                ? ScoreboardScoreEntry::player($scoreboardId->value, $objectiveId->value, $score->value, $actorId->value)
                : ScoreboardScoreEntry::entity($scoreboardId->value, $objectiveId->value, $score->value, $actorId->value);
            $reader = $actorId->reader;
        }
        CodecSupport::requireEnd($reader);

        try {
            return new self($entries);
        } catch (InvalidValueException $exception) {
            throw new MalformedDataException($exception->getMessage(), previous: $exception);
        }
    }
}
