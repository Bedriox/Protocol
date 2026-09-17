<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Tests\Packet;

use PHPUnit\Framework\TestCase;
use Bedriox\Protocol\Exception\CodecException;
use Bedriox\Protocol\Exception\InvalidValueException;
use Bedriox\Protocol\Packet\BedrockPacketCodec;
use Bedriox\Protocol\Packet\CorrectPlayerMovePredictionPacket;
use Bedriox\Protocol\Packet\PacketIds;
use Bedriox\Protocol\Packet\PredictionType;
use Bedriox\Protocol\Packet\ServerSettingsRequestPacket;
use Bedriox\Protocol\Value\UnsignedLong;

final class RetailAdmissionPacketTest extends TestCase
{
    private const string PLAYER_CORRECTION_VECTOR = '000000803f000000400000404000000000000080bf000000000000b442000034420001ac02';
    private const string VEHICLE_CORRECTION_VECTOR = '0100000000000000000000000000000000000000000000000000002041000020c1010000003f0000';

    public function testServerSettingsRequestIsRegisteredAndExactlyEmpty(): void
    {
        $packet = ServerSettingsRequestPacket::decode('');
        self::assertSame('', $packet->encode());
        self::assertSame(PacketIds::SERVER_SETTINGS_REQUEST, BedrockPacketCodec::packetId($packet));
        self::assertEquals($packet, BedrockPacketCodec::decode(PacketIds::SERVER_SETTINGS_REQUEST, ''));

        $this->expectException(CodecException::class);
        ServerSettingsRequestPacket::decode("\0");
    }

    public function testIndependentMovementCorrectionLiteralVectorsRoundTrip(): void
    {
        $player = new CorrectPlayerMovePredictionPacket(
            PredictionType::Player,
            1.0, 2.0, 3.0,
            0.0, -1.0, 0.0,
            90.0, 45.0,
            null,
            true,
            UnsignedLong::fromInt(300),
        );
        $vehicle = new CorrectPlayerMovePredictionPacket(
            PredictionType::Vehicle,
            0.0, 0.0, 0.0,
            0.0, 0.0, 0.0,
            10.0, -10.0,
            0.5,
            false,
            UnsignedLong::fromInt(0),
        );

        self::assertSame(self::PLAYER_CORRECTION_VECTOR, bin2hex($player->encode()));
        self::assertSame(self::VEHICLE_CORRECTION_VECTOR, bin2hex($vehicle->encode()));
        self::assertEquals($player, BedrockPacketCodec::decode(PacketIds::CORRECT_PLAYER_MOVE_PREDICTION, $player->encode()));
        self::assertEquals($vehicle, CorrectPlayerMovePredictionPacket::decode($vehicle->encode()));
        self::assertSame(PacketIds::CORRECT_PLAYER_MOVE_PREDICTION, BedrockPacketCodec::packetId($player));
    }

    public function testEveryMovementCorrectionTruncationAndTrailingByteFailClosed(): void
    {
        foreach ([self::PLAYER_CORRECTION_VECTOR, self::VEHICLE_CORRECTION_VECTOR] as $hex) {
            $wire = hex2bin($hex);
            self::assertIsString($wire);
            for ($length = 0; $length < strlen($wire); ++$length) {
                try {
                    CorrectPlayerMovePredictionPacket::decode(substr($wire, 0, $length));
                    self::fail("Truncated movement correction was accepted at {$length} bytes.");
                } catch (CodecException) {
                    self::addToAssertionCount(1);
                }
            }
            try {
                CorrectPlayerMovePredictionPacket::decode($wire . "\0");
                self::fail('Trailing movement-correction byte was accepted.');
            } catch (CodecException) {
                self::addToAssertionCount(1);
            }
        }
    }

    public function testMovementCorrectionEnumsBooleansAndFiniteFloatsFailClosed(): void
    {
        $player = hex2bin(self::PLAYER_CORRECTION_VECTOR);
        self::assertIsString($player);
        foreach ([
            "\2", // unknown prediction type
            substr($player, 0, 33) . "\2" . substr($player, 34), // angular presence is not boolean
            substr($player, 0, 34) . "\2" . substr($player, 35), // on-ground is not boolean
            substr($player, 0, 33) . "\1" . pack('g', 0.5) . substr($player, 34), // player prediction carries vehicle angular velocity
            "\0" . pack('g', INF), // non-finite position
        ] as $wire) {
            try {
                CorrectPlayerMovePredictionPacket::decode($wire);
                self::fail('Malformed movement correction was accepted.');
            } catch (CodecException) {
                self::addToAssertionCount(1);
            }
        }

        foreach ([
            static fn () => new CorrectPlayerMovePredictionPacket(
                PredictionType::Player,
                0.0, 0.0, 0.0, 0.0, 0.0, 0.0, 0.0, 0.0, 1.0, false, UnsignedLong::fromInt(0),
            ),
            static fn () => new CorrectPlayerMovePredictionPacket(
                PredictionType::Vehicle,
                NAN, 0.0, 0.0, 0.0, 0.0, 0.0, 0.0, 0.0, null, false, UnsignedLong::fromInt(0),
            ),
        ] as $createInvalid) {
            try {
                $createInvalid();
                self::fail('Invalid movement correction was constructed.');
            } catch (InvalidValueException) {
                self::addToAssertionCount(1);
            }
        }
    }
}
