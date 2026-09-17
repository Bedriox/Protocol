<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

use Bedriox\Protocol\Exception\InvalidValueException;
use Bedriox\Protocol\Exception\MalformedDataException;
use Bedriox\Protocol\Value\UnsignedLong;

/** A bounded snapshot of the emote pieces associated with an actor. */
final readonly class EmoteListPacket implements Packet
{
    public const int MAX_EMOTE_IDS = 4_096;

    /** @param list<string> $emoteIds Canonical UUID strings. */
    public function __construct(
        public UnsignedLong $runtimeEntityId,
        public array $emoteIds,
    ) {
        CodecSupport::validateCount($emoteIds, self::MAX_EMOTE_IDS, 'Emote IDs');
        foreach ($emoteIds as $emoteId) {
            if (!is_string($emoteId)) {
                throw new InvalidValueException('Emote IDs must be UUID strings.');
            }
            CodecSupport::uuidToWire($emoteId);
        }
    }

    public function packetId(): int
    {
        return PacketIds::EMOTE_LIST;
    }

    public function encode(): string
    {
        $writer = CodecSupport::writer()
            ->writeUnsignedVarLong($this->runtimeEntityId)
            ->writeUnsignedVarInt(count($this->emoteIds));
        foreach ($this->emoteIds as $emoteId) {
            $writer = $writer->writeBytes(CodecSupport::uuidToWire($emoteId));
        }
        return $writer->toString();
    }

    public static function decode(string $bytes): self
    {
        $runtimeEntityId = CodecSupport::reader($bytes)->readUnsignedVarLong();
        $count = $runtimeEntityId->reader->readUnsignedVarInt();
        if ($count->value > self::MAX_EMOTE_IDS) {
            throw new MalformedDataException('Emote ID count exceeds its limit.');
        }

        $emoteIds = [];
        $reader = $count->reader;
        for ($index = 0; $index < $count->value; ++$index) {
            $uuid = $reader->readBytes(16);
            $emoteIds[] = CodecSupport::uuidFromWire($uuid->value);
            $reader = $uuid->reader;
        }
        CodecSupport::requireEnd($reader);
        return new self($runtimeEntityId->value, $emoteIds);
    }
}
