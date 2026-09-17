<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Tests\Packet;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Bedriox\Protocol\Exception\CodecException;
use Bedriox\Protocol\Exception\InvalidValueException;
use Bedriox\Protocol\Exception\MalformedDataException;
use Bedriox\Protocol\Packet\AuthenticationType;
use Bedriox\Protocol\Packet\ClientCacheStatusPacket;
use Bedriox\Protocol\Packet\ClientToServerHandshakePacket;
use Bedriox\Protocol\Packet\CompressionAlgorithm;
use Bedriox\Protocol\Packet\DisconnectPacket;
use Bedriox\Protocol\Packet\DisconnectReason;
use Bedriox\Protocol\Packet\Experiment;
use Bedriox\Protocol\Packet\LoginAuthentication;
use Bedriox\Protocol\Packet\LoginPacket;
use Bedriox\Protocol\Packet\NetworkSettingsPacket;
use Bedriox\Protocol\Packet\Packet;
use Bedriox\Protocol\Packet\PacketIds;
use Bedriox\Protocol\Packet\PlayStatus;
use Bedriox\Protocol\Packet\PlayStatusPacket;
use Bedriox\Protocol\Packet\RequestNetworkSettingsPacket;
use Bedriox\Protocol\Packet\ResourcePackClientResponsePacket;
use Bedriox\Protocol\Packet\ResourcePackInfoEntry;
use Bedriox\Protocol\Packet\ResourcePackResponseStatus;
use Bedriox\Protocol\Packet\ResourcePacksInfoPacket;
use Bedriox\Protocol\Packet\ResourcePackStackEntry;
use Bedriox\Protocol\Packet\ResourcePackStackPacket;
use Bedriox\Protocol\Packet\ServerToClientHandshakePacket;
use Bedriox\Protocol\Packet\BedrockPacketCodec;

final class PacketCodecTest extends TestCase
{
    /** @return iterable<string, array{Packet, string}> */
    public static function vectors(): iterable
    {
        yield 'request network settings' => [new RequestNetworkSettingsPacket(), '00000891'];
        yield 'network settings' => [
            new NetworkSettingsPacket(256, CompressionAlgorithm::None, true, 5, 1.5),
            '0001020001050000c03f',
        ];
        yield 'login token envelope' => [
            new LoginPacket(2193, new LoginAuthentication(AuthenticationType::Full, 'tok'), 'client'),
            '0000089145370000007b2241757468656e7469636174696f6e54797065223a302c22546f6b656e223a22746f6b222c224365727469666963617465223a22227d06000000636c69656e74',
        ];
        yield 'play status' => [new PlayStatusPacket(PlayStatus::LoginSuccess), '00000000'];
        yield 'server handshake' => [new ServerToClientHandshakePacket('abc'), '03616263'];
        yield 'client handshake' => [new ClientToServerHandshakePacket(), ''];
        yield 'disconnect' => [
            new DisconnectPacket(DisconnectReason::KICKED, false, 'bye'),
            '6e000362796500',
        ];
        yield 'empty pack information' => [
            new ResourcePacksInfoPacket(false, false, false, false, '00000000-0000-0000-0000-000000000000', '1.0', []),
            '000000000000000000000000000000000000000003312e3000',
        ];
        yield 'empty pack stack' => [
            new ResourcePackStackPacket(false, [], '1.26.51', [], false, false),
            '000007312e32362e3531000000000000',
        ];
        yield 'non-empty pack information' => [
            new ResourcePacksInfoPacket(
                true,
                true,
                false,
                true,
                '00112233-4455-6677-8899-aabbccddeeff',
                '1.0.0',
                [new ResourcePackInfoEntry(
                    'ffeeddcc-bbaa-9988-7766-554433221100',
                    '2.0.0',
                    123_456,
                    'key',
                    'sub',
                    'content',
                    true,
                    true,
                    false,
                    'https://example.invalid/pack',
                )],
            ),
            '010100017766554433221100ffeeddccbbaa998805312e302e30018899aabbccddeeff001122334455667705322e302e3040e2010000000000036b65790373756207636f6e74656e740101001c68747470733a2f2f6578616d706c652e696e76616c69642f7061636b',
        ];
        yield 'non-empty pack stack' => [
            new ResourcePackStackPacket(
                true,
                [new ResourcePackStackEntry('pack', '1.0.0', 'sub')],
                '1.26.51',
                [new Experiment('experiment', true)],
                true,
                false,
            ),
            '0101047061636b05312e302e300373756207312e32362e3531010000000a6578706572696d656e74010100',
        ];
        yield 'pack response' => [
            new ResourcePackClientResponsePacket(ResourcePackResponseStatus::Completed, [], 'completed'),
            '0309636f6d706c65746564',
        ];
        yield 'client cache supported' => [new ClientCacheStatusPacket(true), '01'];
    }

    #[DataProvider('vectors')]
    public function testLiteralVectorsAndRoundTrips(Packet $packet, string $hex): void
    {
        $wire = hex2bin($hex);
        self::assertIsString($wire);
        self::assertSame($wire, BedrockPacketCodec::encode($packet));
        self::assertEquals($packet, BedrockPacketCodec::decode($packet->packetId(), $wire));
        self::assertSame($packet->packetId(), BedrockPacketCodec::packetId($packet));
    }

    #[DataProvider('vectors')]
    public function testEveryTruncationAndTrailingByte(Packet $packet, string $hex): void
    {
        $wire = hex2bin($hex);
        self::assertIsString($wire);
        for ($length = 0; $length < strlen($wire); ++$length) {
            try {
                BedrockPacketCodec::decode($packet->packetId(), substr($wire, 0, $length));
                self::fail("Truncation at {$length} bytes was accepted for packet {$packet->packetId()}.");
            } catch (CodecException) {
            }
        }
        $this->expectException(MalformedDataException::class);
        BedrockPacketCodec::decode($packet->packetId(), $wire . "\0");
    }

    public function testResourcePackStructuresRoundTrip(): void
    {
        $info = new ResourcePacksInfoPacket(
            true,
            true,
            false,
            true,
            '00112233-4455-6677-8899-aabbccddeeff',
            '1.0.0',
            [new ResourcePackInfoEntry(
                'ffeeddcc-bbaa-9988-7766-554433221100',
                '2.0.0',
                123_456,
                'key',
                'sub',
                'content',
                true,
                true,
                false,
                'https://example.invalid/pack',
            )],
        );
        self::assertEquals($info, ResourcePacksInfoPacket::decode($info->encode()));

        $stack = new ResourcePackStackPacket(
            true,
            [new ResourcePackStackEntry('pack', '1.0.0', 'sub')],
            '1.26.51',
            [new Experiment('experiment', true)],
            true,
            false,
        );
        self::assertEquals($stack, ResourcePackStackPacket::decode($stack->encode()));
    }

    public function testCertificateAuthenticationRoundTrip(): void
    {
        $packet = new LoginPacket(
            2193,
            new LoginAuthentication(AuthenticationType::SelfSigned, certificateChain: ['first.jwt', 'second.jwt']),
            'client.jwt',
        );
        self::assertEquals($packet, LoginPacket::decode($packet->encode()));
    }

    public function testEmptyClientJwtIsRejectedForCallerAndWireInput(): void
    {
        $authentication = new LoginAuthentication(AuthenticationType::Full, 'token');
        try {
            new LoginPacket(2193, $authentication, '');
            self::fail('Empty caller client JWT was accepted.');
        } catch (InvalidValueException) {
            self::addToAssertionCount(1);
        }

        $valid = (new LoginPacket(2193, $authentication, 'x'))->encode();
        $emptyClientJwt = substr($valid, 0, -5) . pack('V', 0);
        $authenticationEnvelopeLength = ord($emptyClientJwt[4]);
        self::assertGreaterThan(0, $authenticationEnvelopeLength);
        $emptyClientJwt[4] = pack('C', $authenticationEnvelopeLength - 1);
        $this->expectException(MalformedDataException::class);
        LoginPacket::decode($emptyClientJwt);
    }

    /** @return iterable<string, array{int, string}> */
    public static function malformedPayloads(): iterable
    {
        yield 'unknown packet' => [999, ''];
        yield 'network boolean' => [PacketIds::NETWORK_SETTINGS, "\0\0\0\0\2\0\0\0\0\0"];
        yield 'network algorithm' => [PacketIds::NETWORK_SETTINGS, "\0\0\3\0\0\0\0\0\0\0"];
        yield 'play status' => [PacketIds::PLAY_STATUS, "\0\0\0\x0a"];
        yield 'disconnect reason' => [PacketIds::DISCONNECT, "\x98\x02\x01"];
        yield 'disconnect variant' => [PacketIds::DISCONNECT, "\0\2"];
        yield 'pack response status' => [PacketIds::RESOURCE_PACK_CLIENT_RESPONSE, "\x08\0"];
        yield 'pack response count' => [PacketIds::RESOURCE_PACK_CLIENT_RESPONSE, "\x01\0\x81\x02"];
        yield 'client cache boolean' => [PacketIds::CLIENT_CACHE_STATUS, "\x02"];
        yield 'pack info boolean' => [PacketIds::RESOURCE_PACKS_INFO, "\2"];
        yield 'pack info count above maximum' => [
            PacketIds::RESOURCE_PACKS_INFO,
            "\0\0\0\0" . str_repeat("\0", 16) . "\0\x81\x02",
        ];
        yield 'stack negative experiment count' => [PacketIds::RESOURCE_PACK_STACK, "\0\0\0\xff\xff\xff\xff\0\0"];
        yield 'stack count above maximum' => [PacketIds::RESOURCE_PACK_STACK, "\0\x81\x02"];
        yield 'stack experiment count above maximum' => [PacketIds::RESOURCE_PACK_STACK, "\0\0\0\x81\0\0\0"];
        yield 'invalid handshake utf8' => [PacketIds::SERVER_TO_CLIENT_HANDSHAKE, "\1\xff"];
    }

    #[DataProvider('malformedPayloads')]
    public function testMalformedPayloadsAreRejected(int $packetId, string $bytes): void
    {
        $this->expectException(CodecException::class);
        BedrockPacketCodec::decode($packetId, $bytes);
    }

    public function testContradictoryAndEmptyAuthenticationVariantsAreRejected(): void
    {
        foreach ([
            '{"AuthenticationType":0,"Token":"one","Certificate":"two"}',
            '{"AuthenticationType":0,"Token":"","Certificate":""}',
            '{"AuthenticationType":3,"Token":"one","Certificate":""}',
            '{"AuthenticationType":0,"Token":"","Certificate":"{\\"chain\\":[]}"}',
        ] as $json) {
            try {
                LoginAuthentication::fromJson($json);
                self::fail('Invalid authentication envelope was accepted.');
            } catch (MalformedDataException) {
            }
        }

        $this->expectException(InvalidValueException::class);
        new LoginAuthentication(AuthenticationType::Full, 'token', ['certificate']);
    }

    public function testRegistryRejectsUnregisteredPacketOnEncodeAndIdLookup(): void
    {
        $packet = new class implements Packet {
            public function packetId(): int
            {
                return 999;
            }

            public function encode(): string
            {
                return 'unregistered';
            }
        };
        foreach ([
            static fn (): string => BedrockPacketCodec::encode($packet),
            static fn (): int => BedrockPacketCodec::packetId($packet),
        ] as $operation) {
            try {
                $operation();
                self::fail('Unregistered packet type crossed the Bedrock registry boundary.');
            } catch (InvalidValueException) {
                self::addToAssertionCount(1);
            }
        }
    }

    public function testConstructorBoundsAreEnforced(): void
    {
        try {
            new ServerToClientHandshakePacket(str_repeat('x', 1_048_577));
            self::fail('Oversized JWT was accepted.');
        } catch (InvalidValueException) {
        }
        try {
            new ResourcePackClientResponsePacket(ResourcePackResponseStatus::SendPacks, array_fill(0, 257, 'id'));
            self::fail('Oversized pack list was accepted.');
        } catch (InvalidValueException) {
        }
        try {
            $entry = new ResourcePackInfoEntry(
                '00000000-0000-0000-0000-000000000000',
                '',
                0,
                '',
                '',
                '',
                false,
                false,
                false,
                '',
            );
            new ResourcePacksInfoPacket(
                false,
                false,
                false,
                false,
                '00000000-0000-0000-0000-000000000000',
                '',
                array_fill(0, 257, $entry),
            );
            self::fail('Oversized resource-pack information list was accepted.');
        } catch (InvalidValueException) {
            self::addToAssertionCount(1);
        }
        try {
            $entry = new ResourcePackStackEntry('', '', '');
            new ResourcePackStackPacket(false, array_fill(0, 257, $entry), '', [], false, false);
            self::fail('Oversized resource-pack stack was accepted.');
        } catch (InvalidValueException) {
            self::addToAssertionCount(1);
        }
        try {
            $experiment = new Experiment('', false);
            new ResourcePackStackPacket(false, [], '', array_fill(0, 129, $experiment), false, false);
            self::fail('Oversized experiment list was accepted.');
        } catch (InvalidValueException) {
            self::addToAssertionCount(1);
        }
        $this->expectException(InvalidValueException::class);
        new ServerToClientHandshakePacket("\xff");
    }
}
