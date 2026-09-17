<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

use Bedriox\Protocol\Codec\ByteBufferReader;
use Bedriox\Protocol\Codec\ByteBufferWriter;
use Bedriox\Protocol\Exception\MalformedDataException;

final readonly class ResourcePacksInfoPacket implements Packet
{
    /** @param list<ResourcePackInfoEntry> $resourcePacks */
    public function __construct(
        public bool $forcedToAccept,
        public bool $hasAddonPacks,
        public bool $scriptingEnabled,
        public bool $vibrantVisualsForceDisabled,
        public string $worldTemplateId,
        public string $worldTemplateVersion,
        public array $resourcePacks,
    ) {
        CodecSupport::uuidToWire($worldTemplateId);
        CodecSupport::validateString($worldTemplateVersion, CodecSupport::MAX_SHORT_STRING_BYTES, 'World-template version');
        CodecSupport::validateCount($resourcePacks, CodecSupport::MAX_PACKS, 'Resource-pack information');
        foreach ($resourcePacks as $entry) {
            if (!$entry instanceof ResourcePackInfoEntry) {
                throw new \Bedriox\Protocol\Exception\InvalidValueException('Resource-pack information contains an invalid entry.');
            }
        }
    }

    public function packetId(): int
    {
        return PacketIds::RESOURCE_PACKS_INFO;
    }

    public function encode(): string
    {
        $writer = CodecSupport::writeBoolean(CodecSupport::writer(), $this->forcedToAccept);
        $writer = CodecSupport::writeBoolean($writer, $this->hasAddonPacks);
        $writer = CodecSupport::writeBoolean($writer, $this->scriptingEnabled);
        $writer = CodecSupport::writeBoolean($writer, $this->vibrantVisualsForceDisabled)
            ->writeBytes(CodecSupport::uuidToWire($this->worldTemplateId))
            ->writeString($this->worldTemplateVersion, CodecSupport::MAX_SHORT_STRING_BYTES)
            ->writeUnsignedVarInt(count($this->resourcePacks));
        foreach ($this->resourcePacks as $entry) {
            $writer = self::writeEntry($writer, $entry);
        }
        return $writer->toString();
    }

    public static function decode(string $bytes): self
    {
        [$forced, $reader] = CodecSupport::readBoolean(CodecSupport::reader($bytes));
        [$addons, $reader] = CodecSupport::readBoolean($reader);
        [$scripting, $reader] = CodecSupport::readBoolean($reader);
        [$vibrantDisabled, $reader] = CodecSupport::readBoolean($reader);
        $uuid = $reader->readBytes(16);
        $version = $uuid->reader->readString(CodecSupport::MAX_SHORT_STRING_BYTES);
        $count = $version->reader->readUnsignedVarInt();
        if ($count->value > CodecSupport::MAX_PACKS) {
            throw new MalformedDataException('Resource-pack information count exceeds its limit.');
        }
        $packs = [];
        $reader = $count->reader;
        for ($index = 0; $index < $count->value; ++$index) {
            [$packs[], $reader] = self::readEntry($reader);
        }
        CodecSupport::requireEnd($reader);
        return new self(
            $forced,
            $addons,
            $scripting,
            $vibrantDisabled,
            CodecSupport::uuidFromWire($uuid->value),
            $version->value,
            $packs,
        );
    }

    private static function writeEntry(ByteBufferWriter $writer, ResourcePackInfoEntry $entry): ByteBufferWriter
    {
        $writer = $writer->writeBytes(CodecSupport::uuidToWire($entry->packId))
            ->writeString($entry->packVersion, CodecSupport::MAX_SHORT_STRING_BYTES)
            ->writeSignedLongLE($entry->packSize)
            ->writeString($entry->contentKey, CodecSupport::MAX_SHORT_STRING_BYTES)
            ->writeString($entry->subPackName, CodecSupport::MAX_SHORT_STRING_BYTES)
            ->writeString($entry->contentId, CodecSupport::MAX_SHORT_STRING_BYTES);
        $writer = CodecSupport::writeBoolean($writer, $entry->scripting);
        $writer = CodecSupport::writeBoolean($writer, $entry->addonPack);
        $writer = CodecSupport::writeBoolean($writer, $entry->raytracingCapable);
        return $writer->writeString($entry->cdnUrl, CodecSupport::MAX_SHORT_STRING_BYTES);
    }

    /** @return array{ResourcePackInfoEntry, ByteBufferReader} */
    private static function readEntry(ByteBufferReader $reader): array
    {
        $uuid = $reader->readBytes(16);
        $version = $uuid->reader->readString(CodecSupport::MAX_SHORT_STRING_BYTES);
        $size = $version->reader->readSignedLongLE();
        if ($size->value < 0) {
            throw new MalformedDataException('Resource-pack size cannot be negative.');
        }
        $key = $size->reader->readString(CodecSupport::MAX_SHORT_STRING_BYTES);
        $subPack = $key->reader->readString(CodecSupport::MAX_SHORT_STRING_BYTES);
        $contentId = $subPack->reader->readString(CodecSupport::MAX_SHORT_STRING_BYTES);
        [$scripting, $reader] = CodecSupport::readBoolean($contentId->reader);
        [$addon, $reader] = CodecSupport::readBoolean($reader);
        [$raytracing, $reader] = CodecSupport::readBoolean($reader);
        $cdn = $reader->readString(CodecSupport::MAX_SHORT_STRING_BYTES);
        return [new ResourcePackInfoEntry(
            CodecSupport::uuidFromWire($uuid->value),
            $version->value,
            $size->value,
            $key->value,
            $subPack->value,
            $contentId->value,
            $scripting,
            $addon,
            $raytracing,
            $cdn->value,
        ), $cdn->reader];
    }
}
