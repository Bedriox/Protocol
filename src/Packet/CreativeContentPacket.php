<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

use Bedriox\Protocol\Exception\InvalidValueException;
use Bedriox\Protocol\Exception\MalformedDataException;

/** Bounded protocol-2193 creative groups and item entries. */
final readonly class CreativeContentPacket implements Packet
{
    public const int MAXIMUM_GROUPS = 256;
    public const int MAXIMUM_ENTRIES = 65_536;

    /**
     * @param list<CreativeItemGroup> $groups
     * @param list<CreativeItemEntry> $entries
     */
    public function __construct(
        public array $groups = [],
        public array $entries = [],
    ) {
        CodecSupport::validateCount($groups, self::MAXIMUM_GROUPS, 'Creative groups');
        foreach ($groups as $group) {
            if (!$group instanceof CreativeItemGroup) {
                throw new InvalidValueException('Creative groups must contain typed values.');
            }
        }
        CodecSupport::validateCount($entries, self::MAXIMUM_ENTRIES, 'Creative entries');
        $networkIds = [];
        foreach ($entries as $entry) {
            if (!$entry instanceof CreativeItemEntry) {
                throw new InvalidValueException('Creative entries must contain typed values.');
            }
            if (isset($networkIds[$entry->networkId])) {
                throw new InvalidValueException('Creative item network IDs must be unique.');
            }
            if ($entry->groupId >= count($groups)) {
                throw new InvalidValueException('Creative item group ID does not identify a declared group.');
            }
            $networkIds[$entry->networkId] = true;
        }
    }

    public function packetId(): int
    {
        return PacketIds::CREATIVE_CONTENT;
    }

    public function encode(): string
    {
        $writer = CodecSupport::writer()->writeUnsignedVarInt(count($this->groups));
        foreach ($this->groups as $group) {
            $writer = $writer->writeUnsignedByte($group->category->value)
                ->writeString($group->name, CodecSupport::MAX_SHORT_STRING_BYTES);
            $writer = CreativeItemStackWireCodec::write($writer, $group->icon);
        }
        $writer = $writer->writeUnsignedVarInt(count($this->entries));
        foreach ($this->entries as $entry) {
            $writer = CreativeItemStackWireCodec::write($writer->writeUnsignedVarInt($entry->networkId), $entry->item)
                ->writeUnsignedVarInt($entry->groupId);
        }
        return $writer->toString();
    }

    public static function decode(string $bytes): self
    {
        $groupCount = CodecSupport::reader($bytes)->readUnsignedVarInt();
        if ($groupCount->value > self::MAXIMUM_GROUPS) {
            throw new MalformedDataException('Creative group count exceeds its limit.');
        }
        $groups = [];
        $reader = $groupCount->reader;
        for ($index = 0; $index < $groupCount->value; ++$index) {
            $category = $reader->readUnsignedByte();
            $typedCategory = CreativeItemCategory::tryFrom($category->value);
            if ($typedCategory === null) {
                throw new MalformedDataException('Creative group category is unknown.');
            }
            $name = $category->reader->readString(CodecSupport::MAX_SHORT_STRING_BYTES);
            [$icon, $reader] = CreativeItemStackWireCodec::read($name->reader);
            $groups[] = new CreativeItemGroup($typedCategory, $name->value, $icon);
        }
        $entryCount = $reader->readUnsignedVarInt();
        if ($entryCount->value > self::MAXIMUM_ENTRIES) {
            throw new MalformedDataException('Creative item count exceeds its limit.');
        }
        $entries = [];
        $reader = $entryCount->reader;
        try {
            for ($index = 0; $index < $entryCount->value; ++$index) {
                $networkId = $reader->readUnsignedVarInt();
                [$item, $reader] = CreativeItemStackWireCodec::read($networkId->reader);
                $groupId = $reader->readUnsignedVarInt();
                $entries[] = new CreativeItemEntry($networkId->value, $item, $groupId->value);
                $reader = $groupId->reader;
            }
            $packet = new self($groups, $entries);
        } catch (InvalidValueException $e) {
            throw new MalformedDataException('Creative content is invalid.', previous: $e);
        }
        CodecSupport::requireEnd($reader);
        return $packet;
    }
}
