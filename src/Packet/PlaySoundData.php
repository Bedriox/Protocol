<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

use Bedriox\Protocol\Codec\ByteBufferReader;
use Bedriox\Protocol\Codec\ByteBufferWriter;
use Bedriox\Protocol\Exception\InvalidValueException;
use Bedriox\Protocol\Exception\MalformedDataException;

final readonly class PlaySoundData
{
    public function __construct(
        public string $sound,
        public float $x,
        public float $y,
        public float $z,
        public float $volume,
        public float $pitch,
        public int $loopCount = 0,
        public bool $bypassListenerRangeCheck = false,
        public ?int $serverSoundHandle = null,
        public ?float $playbackPositionSeconds = null,
    ) {
        CodecSupport::validateString($sound, CodecSupport::MAX_SHORT_STRING_BYTES, 'Sound identifier');
        foreach ([$x, $y, $z, $volume, $pitch] as $value) {
            CodecSupport::validateFiniteFloat($value, 'Sound value');
        }
        if ($playbackPositionSeconds !== null) {
            CodecSupport::validateFiniteFloat($playbackPositionSeconds, 'Sound playback position');
        }
    }

    public function write(ByteBufferWriter $writer): ByteBufferWriter
    {
        $writer = $writer->writeString($this->sound, CodecSupport::MAX_SHORT_STRING_BYTES)
            ->writeSignedVarInt((int) round($this->x * 8.0))
            ->writeSignedVarInt((int) round($this->y * 8.0))
            ->writeSignedVarInt((int) round($this->z * 8.0))
            ->writeFloatLE($this->volume)
            ->writeFloatLE($this->pitch)
            ->writeSignedVarInt($this->loopCount);
        $writer = CodecSupport::writeBoolean($writer, $this->bypassListenerRangeCheck);
        $writer = CodecSupport::writeBoolean($writer, $this->serverSoundHandle !== null);
        if ($this->serverSoundHandle !== null) {
            $writer = $writer->writeSignedLongLE($this->serverSoundHandle);
        }
        $writer = CodecSupport::writeBoolean($writer, $this->playbackPositionSeconds !== null);
        return $this->playbackPositionSeconds === null ? $writer : $writer->writeFloatLE($this->playbackPositionSeconds);
    }

    /** @return array{self, ByteBufferReader} */
    public static function read(ByteBufferReader $reader): array
    {
        $sound = $reader->readString(CodecSupport::MAX_SHORT_STRING_BYTES);
        $x = $sound->reader->readSignedVarInt();
        $y = $x->reader->readSignedVarInt();
        $z = $y->reader->readSignedVarInt();
        $volume = $z->reader->readFloatLE();
        $pitch = $volume->reader->readFloatLE();
        $loopCount = $pitch->reader->readSignedVarInt();
        [$bypass, $reader] = CodecSupport::readBoolean($loopCount->reader);
        [$hasHandle, $reader] = CodecSupport::readBoolean($reader);
        $handle = null;
        if ($hasHandle) {
            $value = $reader->readSignedLongLE();
            $handle = $value->value;
            $reader = $value->reader;
        }
        [$hasPlaybackPosition, $reader] = CodecSupport::readBoolean($reader);
        $playbackPosition = null;
        if ($hasPlaybackPosition) {
            $value = $reader->readFloatLE();
            $playbackPosition = $value->value;
            $reader = $value->reader;
        }
        try {
            return [new self(
                $sound->value,
                $x->value / 8.0,
                $y->value / 8.0,
                $z->value / 8.0,
                $volume->value,
                $pitch->value,
                $loopCount->value,
                $bypass,
                $handle,
                $playbackPosition,
            ), $reader];
        } catch (InvalidValueException $error) {
            throw new MalformedDataException('Play-sound data is invalid.', previous: $error);
        }
    }
}
