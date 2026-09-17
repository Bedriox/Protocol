<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

use Bedriox\Protocol\Exception\InvalidValueException;
use Bedriox\Protocol\Exception\MalformedDataException;

/** Bedrock player-list REMOVE variant. */
final readonly class PlayerListRemovePacket implements Packet
{
    /** @param list<string> $uuids */
    public function __construct(public array $uuids)
    {
        CodecSupport::validateCount($uuids, CodecSupport::MAX_PLAYER_LIST_ENTRIES, 'Player-list removals');
        foreach ($uuids as $uuid) {
            if (!is_string($uuid)) {
                throw new InvalidValueException('Player-list UUIDs must be strings.');
            }
            CodecSupport::uuidToWire($uuid);
        }
    }

    public function packetId(): int { return PacketIds::PLAYER_LIST; }

    public function encode(): string
    {
        $writer = CodecSupport::writer()->writeUnsignedVarInt(count($this->uuids));
        foreach ($this->uuids as $uuid) {
            $writer = $writer->writeUnsignedVarInt(0)->writeUnsignedByte(1)->writeBytes(CodecSupport::uuidToWire($uuid));
        }
        return $writer->toString();
    }

    public static function decode(string $bytes): self
    {
        $count = CodecSupport::reader($bytes)->readUnsignedVarInt();
        if ($count->value > CodecSupport::MAX_PLAYER_LIST_ENTRIES) {
            throw new MalformedDataException('Player-list removal count exceeds its limit.');
        }
        $uuids = [];
        $reader = $count->reader;
        for ($i = 0; $i < $count->value; ++$i) {
            $recordType = $reader->readUnsignedVarInt();
            $legacyAction = $recordType->reader->readUnsignedByte();
            if ($recordType->value !== 0 || $legacyAction->value !== 1) {
                throw new MalformedDataException('Player-list payload contains a non-REMOVE record.');
            }
            $uuid = $legacyAction->reader->readBytes(16);
            $uuids[] = CodecSupport::uuidFromWire($uuid->value);
            $reader = $uuid->reader;
        }
        CodecSupport::requireEnd($reader);
        return new self($uuids);
    }
}
