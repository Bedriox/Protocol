<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Tests\Packet;

use Bedriox\Protocol\Packet\AudioContentPlaybackType;
use Bedriox\Protocol\Packet\AudioContentRegistrationEntry;
use Bedriox\Protocol\Packet\BedrockPacketCodec;
use Bedriox\Protocol\Packet\BlockPosition;
use Bedriox\Protocol\Packet\ClientboundMatchmakingStatePacket;
use Bedriox\Protocol\Packet\ClientboundPlayAudioContentPacket;
use Bedriox\Protocol\Packet\ClientboundStonecutterSetRecipePacket;
use Bedriox\Protocol\Packet\CursorItemDragState;
use Bedriox\Protocol\Packet\InventoryVector3;
use Bedriox\Protocol\Packet\MatchmakingState;
use Bedriox\Protocol\Packet\MatchmakingStateOptions;
use Bedriox\Protocol\Packet\PacketIds;
use Bedriox\Protocol\Packet\PassengerOfBlockArguments;
use Bedriox\Protocol\Packet\PassengerOfBlockEmoteType;
use Bedriox\Protocol\Packet\PlaySoundData;
use Bedriox\Protocol\Packet\ServerboundCursorItemDragPacket;
use Bedriox\Protocol\Packet\ServerboundMatchmakingCancelPacket;
use Bedriox\Protocol\Packet\ServerboundRegisterAudioContentPacket;
use Bedriox\Protocol\Packet\ServerboundStonecutterSetRecipePacket;
use Bedriox\Protocol\Packet\SetPassengerOfBlockPacket;
use PHPUnit\Framework\TestCase;

final class Protocol2225PacketTest extends TestCase
{
    public function testCurrentPacketIdsAndLiteralVectors(): void
    {
        self::assertSame(353, PacketIds::CLIENTBOUND_MATCHMAKING_STATE);
        self::assertSame(360, PacketIds::SERVERBOUND_REGISTER_AUDIO_CONTENT);

        self::assertSame('0706', bin2hex((new ServerboundStonecutterSetRecipePacket(7, 3))->encode()));
        self::assertSame('540704', bin2hex((new ClientboundStonecutterSetRecipePacket(42, 7, 2))->encode()));
        self::assertSame('', (new ServerboundMatchmakingCancelPacket())->encode());
        self::assertSame('00', bin2hex((new ServerboundCursorItemDragPacket(CursorItemDragState::Start))->encode()));
    }

    public function testNewPacketFamiliesRoundTripThroughTheRegistry(): void
    {
        $packets = [
            new ClientboundMatchmakingStatePacket(
                MatchmakingState::MatchFound,
                'Bedriox',
                new MatchmakingStateOptions('xZeroOfficial', true),
            ),
            new ServerboundStonecutterSetRecipePacket(8, 12),
            new ClientboundStonecutterSetRecipePacket(-15, 8, 12),
            new ServerboundMatchmakingCancelPacket(),
            new SetPassengerOfBlockPacket(31, new PassengerOfBlockArguments(
                new BlockPosition(1, -2, 3),
                new InventoryVector3(0.25, 1.5, -0.5),
                90.0,
                45.0,
                PassengerOfBlockEmoteType::Riding,
            )),
            new ServerboundCursorItemDragPacket(CursorItemDragState::Stop),
            new ClientboundPlayAudioContentPacket(
                'metadata',
                'content',
                AudioContentPlaybackType::Sound,
                new PlaySoundData('note.pling', 1.25, 2.5, -3.75, 1.0, 0.75, 2, true, 9, 1.5),
            ),
            new ServerboundRegisterAudioContentPacket([
                new AudioContentRegistrationEntry('audio-id', 'metadata', 'server-content', 'playback-content'),
            ]),
        ];

        foreach ($packets as $packet) {
            $encoded = BedrockPacketCodec::encode($packet, 2225);
            self::assertEquals(
                $packet,
                BedrockPacketCodec::decode($packet->packetId(), $encoded, 2225),
                $packet::class,
            );
        }
    }
}
