<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

use Bedriox\Protocol\Exception\InvalidValueException;
use Bedriox\Protocol\Exception\MalformedDataException;

final readonly class ClientboundPlayAudioContentPacket implements Packet
{
    private const int MAX_CONTENT_BYTES = 1_048_576;

    public function __construct(
        public string $sharedMetadata,
        public string $playbackContent,
        public AudioContentPlaybackType $playbackType,
        public PlaySoundData $playSound,
    ) {
        CodecSupport::validateString($sharedMetadata, self::MAX_CONTENT_BYTES, 'Audio shared metadata');
        CodecSupport::validateString($playbackContent, self::MAX_CONTENT_BYTES, 'Audio playback content');
    }

    public function packetId(): int
    {
        return PacketIds::CLIENTBOUND_PLAY_AUDIO_CONTENT;
    }

    public function encode(): string
    {
        return $this->playSound->write(CodecSupport::writer()
            ->writeString($this->sharedMetadata, self::MAX_CONTENT_BYTES)
            ->writeString($this->playbackContent, self::MAX_CONTENT_BYTES)
            ->writeString($this->playbackType->value, 16))->toString();
    }

    public static function decode(string $bytes): self
    {
        $metadata = CodecSupport::reader($bytes)->readString(self::MAX_CONTENT_BYTES);
        $content = $metadata->reader->readString(self::MAX_CONTENT_BYTES);
        $type = $content->reader->readString(16);
        $playbackType = AudioContentPlaybackType::tryFrom($type->value)
            ?? throw new MalformedDataException('Audio playback type is unknown.');
        [$sound, $reader] = PlaySoundData::read($type->reader);
        CodecSupport::requireEnd($reader);
        try {
            return new self($metadata->value, $content->value, $playbackType, $sound);
        } catch (InvalidValueException $error) {
            throw new MalformedDataException('Audio content payload is invalid.', previous: $error);
        }
    }
}
