<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Tests\Packet;

use Bedriox\Protocol\Packet\BlockPosition;
use Bedriox\Protocol\Packet\ChunkSerializer;
use Bedriox\Protocol\Packet\CodecSupport;
use Bedriox\Protocol\Packet\CreativeItemStackWireCodec;
use Bedriox\Protocol\Packet\InventoryItemStack;
use Bedriox\Protocol\Packet\InventoryItemStackWireCodec;
use Bedriox\Protocol\Packet\LevelEventPacket;
use Bedriox\Protocol\Packet\LevelEventPosition;
use Bedriox\Protocol\Packet\PalettedStorage;
use Bedriox\Protocol\Packet\UpdateBlockPacket;
use PHPUnit\Framework\TestCase;

final class BlockNetworkHashWireTest extends TestCase
{
    private const int AIR = -604_749_536;

    public function testChunkAndLevelEventUseSignedVarInt(): void
    {
        $signed = CodecSupport::writer()->writeSignedVarInt(self::AIR)->toString();
        self::assertSame("\x01" . $signed, ChunkSerializer::storage(PalettedStorage::singleton(self::AIR, 1)));

        $event = LevelEventPacket::terrainParticle(new LevelEventPosition(0.0, 0.0, 0.0), self::AIR);
        self::assertSame(self::AIR, LevelEventPacket::decode($event->encode())->data);
    }

    public function testUpdateBlockAndInventoryUseUnsignedVarIntOnWire(): void
    {
        $unsigned = CodecSupport::writer()->writeUnsignedVarInt(3_690_217_760)->toString();
        $update = new UpdateBlockPacket(new BlockPosition(0, 0, 0), self::AIR, [], 0);
        self::assertSame("\0\0\0" . $unsigned . "\0\0", $update->encode());
        self::assertSame(self::AIR, UpdateBlockPacket::decode($update->encode())->blockRuntimeId);

        $item = new InventoryItemStack(1, 1, 0, null, self::AIR, '');
        $wire = InventoryItemStackWireCodec::write(CodecSupport::writer(), $item)->toString();
        self::assertStringContainsString($unsigned, $wire);
        [$decoded, $reader] = InventoryItemStackWireCodec::read(CodecSupport::reader($wire));
        self::assertSame(self::AIR, $decoded->blockRuntimeId);
        self::assertSame(0, $reader->remaining());
    }

    public function testCreativeInventoryUsesSignedVarIntOnWire(): void
    {
        $item = new InventoryItemStack(1, 1, 0, null, self::AIR, '');
        $wire = CreativeItemStackWireCodec::write(CodecSupport::writer(), $item)->toString();
        self::assertStringContainsString(CodecSupport::writer()->writeSignedVarInt(self::AIR)->toString(), $wire);
        [$decoded, $reader] = CreativeItemStackWireCodec::read(CodecSupport::reader($wire));
        self::assertSame(self::AIR, $decoded->blockRuntimeId);
        self::assertSame(0, $reader->remaining());
    }
}
