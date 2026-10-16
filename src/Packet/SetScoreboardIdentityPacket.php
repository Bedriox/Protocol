<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

use Bedriox\Protocol\Exception\InvalidValueException;
use Bedriox\Protocol\Exception\MalformedDataException;

final readonly class SetScoreboardIdentityPacket implements Packet
{
    private const int MAXIMUM_ENTRIES = 1_024;

    /** @param list<ScoreboardIdentityEntry> $entries */
    public function __construct(
        public ScoreboardIdentityAction $action,
        public array $entries,
    ) {
        CodecSupport::validateCount($this->entries, self::MAXIMUM_ENTRIES, 'Scoreboard identity entries');
        foreach ($this->entries as $entry) {
            if (!$entry instanceof ScoreboardIdentityEntry) {
                throw new InvalidValueException('Scoreboard identity entries must be typed entry values.');
            }
            if (($this->action === ScoreboardIdentityAction::REGISTER) !== ($entry->playerUniqueId !== null)) {
                throw new InvalidValueException('Scoreboard identity fields do not match the requested action.');
            }
        }
    }

    public function packetId(): int { return PacketIds::SET_SCOREBOARD_IDENTITY; }

    public function encode(): string
    {
        $writer = CodecSupport::writer()
            ->writeUnsignedByte($this->action->value)
            ->writeUnsignedVarInt(count($this->entries));
        foreach ($this->entries as $entry) {
            $writer = $writer->writeSignedVarLong($entry->scoreboardId);
            $hasPlayer = $entry->playerUniqueId !== null;
            $writer = CodecSupport::writeBoolean($writer, $hasPlayer);
            if ($hasPlayer) {
                $writer = $writer->writeSignedVarLong($entry->playerUniqueId);
            }
        }
        return $writer->toString();
    }

    public static function decode(string $bytes): self
    {
        $actionWire = CodecSupport::reader($bytes)->readUnsignedByte();
        $action = ScoreboardIdentityAction::tryFrom($actionWire->value);
        if ($action === null) {
            throw new MalformedDataException('Unknown scoreboard identity action.');
        }
        $count = $actionWire->reader->readUnsignedVarInt();
        if ($count->value > self::MAXIMUM_ENTRIES) {
            throw new MalformedDataException('Scoreboard identity entry count exceeds its limit.');
        }

        $entries = [];
        $reader = $count->reader;
        for ($index = 0; $index < $count->value; ++$index) {
            $scoreboardId = $reader->readSignedVarLong();
            [$hasPlayer, $reader] = CodecSupport::readBoolean($scoreboardId->reader);
            if (($action === ScoreboardIdentityAction::REGISTER) !== $hasPlayer) {
                throw new MalformedDataException('Scoreboard identity optional player field does not match its action.');
            }
            $playerId = null;
            if ($hasPlayer) {
                $player = $reader->readSignedVarLong();
                $playerId = $player->value;
                $reader = $player->reader;
            }
            $entries[] = new ScoreboardIdentityEntry($scoreboardId->value, $playerId);
        }
        CodecSupport::requireEnd($reader);

        return new self($action, $entries);
    }
}
