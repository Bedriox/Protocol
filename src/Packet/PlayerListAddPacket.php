<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

use Bedriox\Protocol\Exception\InvalidValueException;
use Bedriox\Protocol\Exception\MalformedDataException;
use Bedriox\Protocol\Value\BuildPlatform;

final readonly class PlayerListAddPacket implements Packet
{
    /** @param list<PlayerListAddEntry> $entries */
    public function __construct(public array $entries)
    {
        CodecSupport::validateCount($entries, CodecSupport::MAX_PLAYER_LIST_ENTRIES, 'Player-list additions');
        foreach ($entries as $entry) {
            if (!$entry instanceof PlayerListAddEntry) { throw new InvalidValueException('Player-list additions must be entry values.'); }
        }
    }

    public function packetId(): int { return PacketIds::PLAYER_LIST; }

    public function encode(): string
    {
        $writer = CodecSupport::writer()->writeUnsignedVarInt(count($this->entries));
        foreach ($this->entries as $entry) {
            $writer = $writer->writeUnsignedVarInt(1)->writeUnsignedByte(0)
                ->writeBytes(CodecSupport::uuidToWire($entry->uuid))->writeSignedVarLong($entry->uniqueEntityId)
                ->writeString($entry->name, CodecSupport::MAX_PLAYER_NAME_BYTES)->writeString($entry->xuid, CodecSupport::MAX_SHORT_STRING_BYTES)
                ->writeString($entry->platformChatId, CodecSupport::MAX_SHORT_STRING_BYTES)->writeSignedIntLE($entry->buildPlatform->value);
            $writer = $entry->skin->write($writer, $entry->trustedSkin);
            $writer = CodecSupport::writeBoolean($writer, $entry->teacher);
            $writer = CodecSupport::writeBoolean($writer, $entry->host);
            $writer = CodecSupport::writeBoolean($writer, $entry->subClient)->writeUnsignedIntLE($entry->colorArgb);
        }
        return $writer->toString();
    }

    public static function decode(string $bytes): self
    {
        $count = CodecSupport::reader($bytes)->readUnsignedVarInt();
        if ($count->value > CodecSupport::MAX_PLAYER_LIST_ENTRIES) { throw new MalformedDataException('Player-list addition count exceeds its limit.'); }
        $base = []; $reader = $count->reader;
        for ($i = 0; $i < $count->value; ++$i) {
            $recordType = $reader->readUnsignedVarInt();
            $legacyAction = $recordType->reader->readUnsignedByte();
            if ($recordType->value !== 1 || $legacyAction->value !== 0) {
                throw new MalformedDataException('Player-list payload contains a non-ADD record.');
            }
            $uuid = $legacyAction->reader->readBytes(16); $entityId = $uuid->reader->readSignedVarLong();
            $name = $entityId->reader->readString(CodecSupport::MAX_PLAYER_NAME_BYTES); $xuid = $name->reader->readString(CodecSupport::MAX_SHORT_STRING_BYTES);
            $platformChat = $xuid->reader->readString(CodecSupport::MAX_SHORT_STRING_BYTES); $platform = $platformChat->reader->readSignedIntLE();
            $buildPlatform = BuildPlatform::tryFrom($platform->value);
            if ($buildPlatform === null) { throw new MalformedDataException('Player-list build platform is invalid.'); }
            [$skin, $reader, $trusted] = PlayerSkin::read($platform->reader);
            [$teacher, $reader] = CodecSupport::readBoolean($reader); [$host, $reader] = CodecSupport::readBoolean($reader);
            [$subClient, $reader] = CodecSupport::readBoolean($reader); $color = $reader->readUnsignedIntLE(); $reader = $color->reader;
            $base[] = new PlayerListAddEntry(CodecSupport::uuidFromWire($uuid->value), $entityId->value, $name->value, $xuid->value,
                $platformChat->value, $buildPlatform, $skin, $teacher, $host, $subClient, $color->value, $trusted);
        }
        CodecSupport::requireEnd($reader);
        return new self($base);
    }
}
