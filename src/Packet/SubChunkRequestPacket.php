<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

use Bedriox\Protocol\Exception\InvalidValueException;
use Bedriox\Protocol\Exception\MalformedDataException;

/** Bounded modern client request for sub-chunks relative to one absolute section center. */
final readonly class SubChunkRequestPacket implements Packet
{
    /** @param list<array{x: int, y: int, z: int}> $offsets */
    public function __construct(
        public int $dimension,
        public int $centerX,
        public int $centerY,
        public int $centerZ,
        public array $offsets,
    ) {
        foreach ([$dimension, $centerX, $centerY, $centerZ] as $value) {
            if ($value < -0x80000000 || $value > 0x7fffffff) {
                throw new InvalidValueException('Sub-chunk request coordinate must fit in 32 bits.');
            }
        }
        if (!array_is_list($offsets) || $offsets === [] || count($offsets) > 8_192) {
            throw new InvalidValueException('Sub-chunk request offsets must be a non-empty bounded list.');
        }
        foreach ($offsets as $offset) {
            if (array_keys($offset) !== ['x', 'y', 'z']) {
                throw new InvalidValueException('Sub-chunk request offset shape is invalid.');
            }
            foreach ($offset as $value) {
                if ($value < -128 || $value > 127) {
                    throw new InvalidValueException('Sub-chunk request offset must fit in a signed byte.');
                }
            }
        }
    }

    public function packetId(): int { return PacketIds::SUB_CHUNK_REQUEST; }

    public function encode(): string
    {
        $writer = CodecSupport::writer()->writeSignedVarInt($this->dimension)->writeUnsignedVarInt(count($this->offsets));
        foreach ($this->offsets as $offset) {
            $writer = $writer->writeUnsignedByte($offset['x'] & 0xff)
                ->writeUnsignedByte($offset['y'] & 0xff)->writeUnsignedByte($offset['z'] & 0xff);
        }
        return $writer->writeSignedIntLE($this->centerX)->writeSignedIntLE($this->centerY)
            ->writeSignedIntLE($this->centerZ)->toString();
    }

    public static function decode(string $bytes): self
    {
        $dimension = CodecSupport::reader($bytes)->readSignedVarInt();
        $count = $dimension->reader->readUnsignedVarInt();
        if ($count->value < 1 || $count->value > 8_192) {
            throw new MalformedDataException('Sub-chunk request offset count is invalid.');
        }
        $reader = $count->reader;
        $offsets = [];
        for ($index = 0; $index < $count->value; ++$index) {
            $x = $reader->readUnsignedByte(); $y = $x->reader->readUnsignedByte(); $z = $y->reader->readUnsignedByte();
            $offsets[] = [
                'x' => $x->value >= 128 ? $x->value - 256 : $x->value,
                'y' => $y->value >= 128 ? $y->value - 256 : $y->value,
                'z' => $z->value >= 128 ? $z->value - 256 : $z->value,
            ];
            $reader = $z->reader;
        }
        $centerX = $reader->readSignedIntLE(); $centerY = $centerX->reader->readSignedIntLE();
        $centerZ = $centerY->reader->readSignedIntLE();
        CodecSupport::requireEnd($centerZ->reader);
        return new self($dimension->value, $centerX->value, $centerY->value, $centerZ->value, $offsets);
    }
}
