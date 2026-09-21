<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

use Bedriox\Protocol\Exception\MalformedDataException;

/** Clientbound title, subtitle, action-bar, timing, clear, and reset operation. */
final readonly class SetTitlePacket implements Packet
{
    public function __construct(
        public SetTitleType $type,
        public string $text = '',
        public int $fadeInTicks = 0,
        public int $stayTicks = 0,
        public int $fadeOutTicks = 0,
        public string $xuid = '',
        public string $platformOnlineId = '',
        public string $filteredText = '',
    ) {
        CodecSupport::validateString($text, CodecSupport::MAX_SHORT_STRING_BYTES, 'Title text');
        CodecSupport::validateString($xuid, CodecSupport::MAX_SHORT_STRING_BYTES, 'Title XUID');
        CodecSupport::validateString($platformOnlineId, CodecSupport::MAX_SHORT_STRING_BYTES, 'Title platform online ID');
        CodecSupport::validateString($filteredText, CodecSupport::MAX_SHORT_STRING_BYTES, 'Filtered title text');
    }

    public static function title(string $text): self
    {
        return new self(SetTitleType::Title, $text);
    }

    public static function subtitle(string $text): self
    {
        return new self(SetTitleType::Subtitle, $text);
    }

    public static function actionBar(string $text): self
    {
        return new self(SetTitleType::ActionBar, $text);
    }

    public static function times(int $fadeInTicks, int $stayTicks, int $fadeOutTicks): self
    {
        return new self(SetTitleType::Times, fadeInTicks: $fadeInTicks, stayTicks: $stayTicks, fadeOutTicks: $fadeOutTicks);
    }

    public static function clear(): self
    {
        return new self(SetTitleType::Clear);
    }

    public static function reset(): self
    {
        return new self(SetTitleType::Reset);
    }

    public static function titleJson(string $json): self
    {
        return new self(SetTitleType::TitleJson, $json);
    }

    public static function subtitleJson(string $json): self
    {
        return new self(SetTitleType::SubtitleJson, $json);
    }

    public static function actionBarJson(string $json): self
    {
        return new self(SetTitleType::ActionBarJson, $json);
    }

    public function packetId(): int
    {
        return PacketIds::SET_TITLE;
    }

    public function encode(): string
    {
        return CodecSupport::writer()
            ->writeSignedVarInt($this->type->value)
            ->writeString($this->text, CodecSupport::MAX_SHORT_STRING_BYTES)
            ->writeSignedVarInt($this->fadeInTicks)
            ->writeSignedVarInt($this->stayTicks)
            ->writeSignedVarInt($this->fadeOutTicks)
            ->writeString($this->xuid, CodecSupport::MAX_SHORT_STRING_BYTES)
            ->writeString($this->platformOnlineId, CodecSupport::MAX_SHORT_STRING_BYTES)
            ->writeString($this->filteredText, CodecSupport::MAX_SHORT_STRING_BYTES)
            ->toString();
    }

    public static function decode(string $bytes): self
    {
        $typeWire = CodecSupport::reader($bytes)->readSignedVarInt();
        $type = SetTitleType::tryFrom($typeWire->value);
        if ($type === null) {
            throw new MalformedDataException('Unknown SetTitle operation.');
        }
        $text = $typeWire->reader->readString(CodecSupport::MAX_SHORT_STRING_BYTES);
        $fadeIn = $text->reader->readSignedVarInt();
        $stay = $fadeIn->reader->readSignedVarInt();
        $fadeOut = $stay->reader->readSignedVarInt();
        $xuid = $fadeOut->reader->readString(CodecSupport::MAX_SHORT_STRING_BYTES);
        $platform = $xuid->reader->readString(CodecSupport::MAX_SHORT_STRING_BYTES);
        $filtered = $platform->reader->readString(CodecSupport::MAX_SHORT_STRING_BYTES);
        CodecSupport::requireEnd($filtered->reader);

        return new self(
            $type,
            $text->value,
            $fadeIn->value,
            $stay->value,
            $fadeOut->value,
            $xuid->value,
            $platform->value,
            $filtered->value,
        );
    }
}
