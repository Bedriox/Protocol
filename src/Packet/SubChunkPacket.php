<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

use Bedriox\Protocol\Exception\InvalidValueException;
use Bedriox\Protocol\ProtocolVersion;

/** Clientbound bounded sub-chunk response batch with cache blobs disabled. */
final readonly class SubChunkPacket implements Packet
{
    /** @param list<SubChunkResponse> $responses */
    public function __construct(
        public int $dimension,
        public int $centerX,
        public int $centerY,
        public int $centerZ,
        public array $responses,
    ) {
        if ($dimension < -0x80000000 || $dimension > 0x7fffffff
            || !array_is_list($responses) || $responses === [] || count($responses) > 8_192) {
            throw new InvalidValueException('Sub-chunk response batch is invalid.');
        }
        foreach ($responses as $response) {
            if (!$response instanceof SubChunkResponse) {
                throw new InvalidValueException('Sub-chunk response batch contains an invalid entry.');
            }
        }
    }

    public function packetId(): int { return PacketIds::SUB_CHUNK; }
    public function encode(): string { return $this->encodeForProtocol(ProtocolVersion::CURRENT); }

    /**
     * @param array{air: int, bedrock: int, dirt: int, grass_block: int} $runtimeIds
     */
    public static function fixedFlat(SubChunkRequestPacket $request, array $runtimeIds, int $chunkRadius): self
    {
        $keys = array_keys($runtimeIds); sort($keys);
        if ($keys !== ['air', 'bedrock', 'dirt', 'grass_block'] || count(array_unique($runtimeIds)) !== 4
            || $chunkRadius < 0 || $chunkRadius > 32) {
            throw new InvalidValueException('Fixed-flat sub-chunk inputs are invalid.');
        }
        $sectionData = self::fixedFlatSectionData($runtimeIds);
        $responses = [];
        foreach ($request->offsets as $offset) {
            $chunkX = $request->centerX + $offset['x'];
            $sectionY = $request->centerY + $offset['y'];
            $chunkZ = $request->centerZ + $offset['z'];
            if (abs($chunkX) > $chunkRadius || abs($chunkZ) > $chunkRadius) {
                $responses[] = new SubChunkResponse($offset['x'], $offset['y'], $offset['z'],
                    SubChunkResponse::CHUNK_NOT_FOUND, null, SubChunkResponse::HEIGHT_NONE, null,
                    SubChunkResponse::HEIGHT_NONE, null);
                continue;
            }
            if ($sectionY < LevelChunkPacket::OVERWORLD_MIN_SECTION_Y || $sectionY > LevelChunkPacket::OVERWORLD_MAX_SECTION_Y) {
                $responses[] = new SubChunkResponse($offset['x'], $offset['y'], $offset['z'],
                    SubChunkResponse::INDEX_OUT_OF_BOUNDS, null, SubChunkResponse::HEIGHT_NONE, null,
                    SubChunkResponse::HEIGHT_NONE, null);
                continue;
            }
            if ($sectionY === LevelChunkPacket::FIXED_FLAT_SECTION_Y) {
                $heightMap = str_repeat("\x0f", 256);
                $responses[] = new SubChunkResponse($offset['x'], $offset['y'], $offset['z'],
                    SubChunkResponse::SUCCESS, $sectionData, SubChunkResponse::HEIGHT_DATA, $heightMap,
                    SubChunkResponse::HEIGHT_DATA, $heightMap);
                continue;
            }
            $heightType = $sectionY < LevelChunkPacket::FIXED_FLAT_SECTION_Y
                ? SubChunkResponse::HEIGHT_TOO_HIGH : SubChunkResponse::HEIGHT_TOO_LOW;
            $responses[] = new SubChunkResponse($offset['x'], $offset['y'], $offset['z'],
                SubChunkResponse::SUCCESS_ALL_AIR, null, $heightType, null, $heightType, null);
        }
        return new self($request->dimension, $request->centerX, $request->centerY, $request->centerZ, $responses);
    }

    public function encodeForProtocol(int $protocolVersion): string
    {
        if (!ProtocolVersion::supports($protocolVersion)) {
            throw new InvalidValueException('Unsupported protocol version for SubChunk.');
        }
        $writer = CodecSupport::writeBoolean(CodecSupport::writer(), false)
            ->writeSignedVarInt($this->dimension)->writeSignedIntLE($this->centerX)
            ->writeSignedIntLE($this->centerY)->writeSignedIntLE($this->centerZ)
            ->writeUnsignedVarInt(count($this->responses));
        foreach ($this->responses as $response) {
            $writer = $writer->writeUnsignedByte($response->offsetX & 0xff)
                ->writeUnsignedByte($response->offsetY & 0xff)->writeUnsignedByte($response->offsetZ & 0xff)
                ->writeUnsignedByte($response->result);
            $writer = CodecSupport::writeBoolean($writer, $response->data !== null);
            if ($response->data !== null) {
                $writer = $writer->writeUnsignedVarInt(strlen($response->data))->writeBytes($response->data);
            }
            $writer = self::writeHeightMap($writer, $response->heightMapType, $response->heightMap);
            $writer = self::writeHeightMap($writer, $response->renderHeightMapType, $response->renderHeightMap);
            $writer = CodecSupport::writeBoolean($writer, false);
        }
        return $writer->toString();
    }

    private static function writeHeightMap(\Bedriox\Protocol\Codec\ByteBufferWriter $writer, int $type, ?string $map): \Bedriox\Protocol\Codec\ByteBufferWriter
    {
        $writer = $writer->writeUnsignedByte($type);
        $writer = CodecSupport::writeBoolean($writer, $map !== null);
        if ($map === null) {
            return $writer;
        }
        for ($offset = 0; $offset < 256; $offset += 16) {
            $writer = $writer->writeUnsignedVarInt(16)->writeBytes(substr($map, $offset, 16));
        }
        return $writer;
    }

    /** @param array{air: int, bedrock: int, dirt: int, grass_block: int} $runtimeIds */
    private static function fixedFlatSectionData(array $runtimeIds): string
    {
        $values = array_fill(0, ChunkSectionData::CELL_COUNT, $runtimeIds['air']);
        for ($x = 0; $x < 16; ++$x) {
            for ($z = 0; $z < 16; ++$z) {
                $base = ($x << 8) | ($z << 4);
                $values[$base | 12] = $runtimeIds['bedrock'];
                $values[$base | 13] = $runtimeIds['dirt'];
                $values[$base | 14] = $runtimeIds['dirt'];
                $values[$base | 15] = $runtimeIds['grass_block'];
            }
        }
        return ChunkSerializer::section(ChunkSectionData::fromRuntimeIds(
            LevelChunkPacket::FIXED_FLAT_SECTION_Y,
            array_values($values),
        ));
    }
}
