<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

use Bedriox\Protocol\Exception\InvalidValueException;
use Bedriox\Protocol\Exception\MalformedDataException;

final readonly class ServerboundRegisterAudioContentPacket implements Packet
{
    private const int MAX_REGISTRATIONS = 64;
    private const int MAX_CONTENT_BYTES = 1_048_576;

    /** @param list<AudioContentRegistrationEntry> $registrations */
    public function __construct(public array $registrations)
    {
        CodecSupport::validateCount($registrations, self::MAX_REGISTRATIONS, 'Audio content registrations');
        foreach ($registrations as $registration) {
            if (!$registration instanceof AudioContentRegistrationEntry) {
                throw new InvalidValueException('Audio content registrations must contain registration entries.');
            }
        }
    }

    public function packetId(): int
    {
        return PacketIds::SERVERBOUND_REGISTER_AUDIO_CONTENT;
    }

    public function encode(): string
    {
        $writer = CodecSupport::writer()->writeUnsignedVarInt(count($this->registrations));
        foreach ($this->registrations as $registration) {
            $writer = $writer->writeString($registration->audioContentId, CodecSupport::MAX_SHORT_STRING_BYTES)
                ->writeString($registration->sharedMetadata, self::MAX_CONTENT_BYTES)
                ->writeString($registration->serverContent, self::MAX_CONTENT_BYTES)
                ->writeString($registration->playbackContent, self::MAX_CONTENT_BYTES);
        }
        return $writer->toString();
    }

    public static function decode(string $bytes): self
    {
        $count = CodecSupport::reader($bytes)->readUnsignedVarInt();
        if ($count->value > self::MAX_REGISTRATIONS) {
            throw new MalformedDataException('Audio content registration count exceeds its limit.');
        }
        $reader = $count->reader;
        $registrations = [];
        try {
            for ($index = 0; $index < $count->value; ++$index) {
                $id = $reader->readString(CodecSupport::MAX_SHORT_STRING_BYTES);
                $metadata = $id->reader->readString(self::MAX_CONTENT_BYTES);
                $server = $metadata->reader->readString(self::MAX_CONTENT_BYTES);
                $playback = $server->reader->readString(self::MAX_CONTENT_BYTES);
                $registrations[] = new AudioContentRegistrationEntry(
                    $id->value,
                    $metadata->value,
                    $server->value,
                    $playback->value,
                );
                $reader = $playback->reader;
            }
            CodecSupport::requireEnd($reader);
            return new self($registrations);
        } catch (InvalidValueException $error) {
            throw new MalformedDataException('Audio content registration is invalid.', previous: $error);
        }
    }
}
