<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

use Bedriox\Protocol\Exception\InvalidValueException;
use Bedriox\Protocol\Exception\MalformedDataException;

final readonly class MapInfoRequestPacket implements Packet
{
    public const int MAXIMUM_PIXELS = 16_384;

    /** @var list<MapPixel> */
    public array $pixels;

    /** @param list<MapPixel> $pixels */
    public function __construct(public int $uniqueMapId, array $pixels = [])
    {
        if (!array_is_list($pixels) || count($pixels) > self::MAXIMUM_PIXELS) {
            throw new InvalidValueException('Map-info pixel list exceeds its limit.');
        }
        foreach ($pixels as $pixel) {
            if (!$pixel instanceof MapPixel) {
                throw new InvalidValueException('Map-info pixel list contains an invalid value.');
            }
        }
        $this->pixels = $pixels;
    }

    public function packetId(): int { return PacketIds::MAP_INFO_REQUEST; }

    public function encode(): string
    {
        $writer = CodecSupport::writer()->writeSignedVarLong($this->uniqueMapId)
            ->writeSignedIntLE(count($this->pixels));
        foreach ($this->pixels as $pixel) {
            $writer = $writer->writeSignedIntLE($pixel->color)->writeUnsignedShortLE($pixel->index);
        }
        return $writer->toString();
    }

    public static function decode(string $bytes): self
    {
        $mapId = CodecSupport::reader($bytes)->readSignedVarLong();
        $count = $mapId->reader->readSignedIntLE();
        if ($count->value < 0 || $count->value > self::MAXIMUM_PIXELS) {
            throw new MalformedDataException('Map-info pixel count exceeds its limit.');
        }
        $reader = $count->reader;
        $pixels = [];
        for ($index = 0; $index < $count->value; ++$index) {
            $color = $reader->readSignedIntLE();
            $pixelIndex = $color->reader->readUnsignedShortLE();
            $pixels[] = new MapPixel($color->value, $pixelIndex->value);
            $reader = $pixelIndex->reader;
        }
        CodecSupport::requireEnd($reader);
        return new self($mapId->value, $pixels);
    }
}
