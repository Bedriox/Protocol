<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

use Bedriox\Protocol\Exception\InvalidValueException;
use Bedriox\Protocol\Exception\MalformedDataException;
use Bedriox\Protocol\Value\UnsignedLong;

/** Immutable emote notification; authorization and broadcast policy belong to the server. */
final readonly class EmotePacket implements Packet
{
    /** @param list<EmoteFlag> $flags */
    public function __construct(
        public UnsignedLong $runtimeEntityId,
        public string $emoteId,
        public int $emoteLengthTicks,
        public string $xuid,
        public string $platformId,
        public array $flags = [],
    ) {
        CodecSupport::validateString($emoteId, CodecSupport::MAX_SHORT_STRING_BYTES, 'Emote ID');
        CodecSupport::validateString($xuid, CodecSupport::MAX_SHORT_STRING_BYTES, 'Emote XUID');
        CodecSupport::validateString($platformId, CodecSupport::MAX_SHORT_STRING_BYTES, 'Emote platform ID');
        if ($emoteLengthTicks < 0 || $emoteLengthTicks > 0xffffffff) {
            throw new InvalidValueException('Emote length must fit an unsigned 32-bit integer.');
        }
        if (!array_is_list($flags)) {
            throw new InvalidValueException('Emote flags must be a list.');
        }
        $seen = 0;
        foreach ($flags as $flag) {
            if (!$flag instanceof EmoteFlag || ($seen & (1 << $flag->value)) !== 0) {
                throw new InvalidValueException('Emote flags must be unique typed values.');
            }
            $seen |= 1 << $flag->value;
        }
    }

    public function packetId(): int { return PacketIds::EMOTE; }

    public function encode(): string
    {
        $flagBits = 0;
        foreach ($this->flags as $flag) {
            $flagBits |= 1 << $flag->value;
        }
        return CodecSupport::writer()->writeUnsignedVarLong($this->runtimeEntityId)
            ->writeString($this->emoteId, CodecSupport::MAX_SHORT_STRING_BYTES)
            ->writeUnsignedVarInt($this->emoteLengthTicks)
            ->writeString($this->xuid, CodecSupport::MAX_SHORT_STRING_BYTES)
            ->writeString($this->platformId, CodecSupport::MAX_SHORT_STRING_BYTES)
            ->writeUnsignedByte($flagBits)->toString();
    }

    public static function decode(string $bytes): self
    {
        $runtime = CodecSupport::reader($bytes)->readUnsignedVarLong();
        $emoteId = $runtime->reader->readString(CodecSupport::MAX_SHORT_STRING_BYTES);
        $length = $emoteId->reader->readUnsignedVarInt();
        $xuid = $length->reader->readString(CodecSupport::MAX_SHORT_STRING_BYTES);
        $platformId = $xuid->reader->readString(CodecSupport::MAX_SHORT_STRING_BYTES);
        $flagBits = $platformId->reader->readUnsignedByte();
        if (($flagBits->value & ~0x03) !== 0) {
            throw new MalformedDataException('Emote flags contain unknown bits.');
        }
        $flags = [];
        foreach (EmoteFlag::cases() as $flag) {
            if (($flagBits->value & (1 << $flag->value)) !== 0) {
                $flags[] = $flag;
            }
        }
        CodecSupport::requireEnd($flagBits->reader);
        return new self($runtime->value, $emoteId->value, $length->value, $xuid->value, $platformId->value, $flags);
    }
}
