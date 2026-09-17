<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

use Bedriox\Protocol\Exception\MalformedDataException;

final readonly class ResourcePackStackPacket implements Packet
{
    /**
     * @param list<ResourcePackStackEntry> $resourcePacks
     * @param list<Experiment> $experiments
     */
    public function __construct(
        public bool $forcedToAccept,
        public array $resourcePacks,
        public string $gameVersion,
        public array $experiments,
        public bool $experimentsPreviouslyToggled,
        public bool $hasEditorPacks,
    ) {
        CodecSupport::validateCount($resourcePacks, CodecSupport::MAX_PACKS, 'Resource-pack stack');
        CodecSupport::validateString($gameVersion, CodecSupport::MAX_SHORT_STRING_BYTES, 'Game version');
        CodecSupport::validateCount($experiments, CodecSupport::MAX_EXPERIMENTS, 'Experiments');
        foreach ($resourcePacks as $entry) {
            if (!$entry instanceof ResourcePackStackEntry) {
                throw new \Bedriox\Protocol\Exception\InvalidValueException('Resource-pack stack contains an invalid entry.');
            }
        }
        foreach ($experiments as $experiment) {
            if (!$experiment instanceof Experiment) {
                throw new \Bedriox\Protocol\Exception\InvalidValueException('Experiment list contains an invalid entry.');
            }
        }
    }

    public function packetId(): int
    {
        return PacketIds::RESOURCE_PACK_STACK;
    }

    public function encode(): string
    {
        $writer = CodecSupport::writeBoolean(CodecSupport::writer(), $this->forcedToAccept);
        $writer = CodecSupport::writeStackEntries($writer, $this->resourcePacks)
            ->writeString($this->gameVersion, CodecSupport::MAX_SHORT_STRING_BYTES)
            ->writeSignedIntLE(count($this->experiments));
        foreach ($this->experiments as $experiment) {
            $writer = $writer->writeString($experiment->name, CodecSupport::MAX_SHORT_STRING_BYTES);
            $writer = CodecSupport::writeBoolean($writer, $experiment->enabled);
        }
        $writer = CodecSupport::writeBoolean($writer, $this->experimentsPreviouslyToggled);
        $writer = CodecSupport::writeBoolean($writer, $this->hasEditorPacks);
        return $writer->toString();
    }

    public static function decode(string $bytes): self
    {
        [$forced, $reader] = CodecSupport::readBoolean(CodecSupport::reader($bytes));
        [$packs, $reader] = CodecSupport::readStackEntries($reader);
        $version = $reader->readString(CodecSupport::MAX_SHORT_STRING_BYTES);
        $count = $version->reader->readSignedIntLE();
        if ($count->value < 0 || $count->value > CodecSupport::MAX_EXPERIMENTS) {
            throw new MalformedDataException('Experiment count is invalid.');
        }
        $experiments = [];
        $reader = $count->reader;
        for ($index = 0; $index < $count->value; ++$index) {
            $name = $reader->readString(CodecSupport::MAX_SHORT_STRING_BYTES);
            [$enabled, $reader] = CodecSupport::readBoolean($name->reader);
            $experiments[] = new Experiment($name->value, $enabled);
        }
        [$previouslyToggled, $reader] = CodecSupport::readBoolean($reader);
        [$editorPacks, $reader] = CodecSupport::readBoolean($reader);
        CodecSupport::requireEnd($reader);
        return new self($forced, $packs, $version->value, $experiments, $previouslyToggled, $editorPacks);
    }
}
