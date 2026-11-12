<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Tests\GatewayControl;

use Bedriox\Protocol\Exception\CryptographicException;
use Bedriox\Protocol\Exception\MalformedDataException;
use Bedriox\Protocol\GatewayControl\ControlFrame;
use Bedriox\Protocol\GatewayControl\ControlFrameCipher;
use Bedriox\Protocol\GatewayControl\ControlHandshakeClaims;
use Bedriox\Protocol\GatewayControl\ControlHandshakeStage;
use Bedriox\Protocol\GatewayControl\ControlHandshakeToken;
use Bedriox\Protocol\GatewayControl\ControlMessageType;
use PHPUnit\Framework\TestCase;

final class ControlHandshakeTokenTest extends TestCase
{
    public function testAuthenticatedHandshakeDerivesStableSessionKey(): void
    {
        $claims = new ControlHandshakeClaims(
            ControlHandshakeStage::Challenge,
            'bedriox-gateway',
            'arcade',
            'primary',
            ControlHandshakeClaims::nonce(),
            ControlHandshakeClaims::nonce(),
            1_000,
            1_015,
        );
        $secret = str_repeat("\x5a", 32);

        $decoded = ControlHandshakeToken::verify(ControlHandshakeToken::issue($claims, $secret), $secret);

        self::assertEquals($claims, $decoded);
        self::assertSame(
            ControlHandshakeToken::deriveSessionKey($secret, $claims),
            ControlHandshakeToken::deriveSessionKey($secret, $decoded),
        );
    }

    public function testTamperedHandshakeFailsClosed(): void
    {
        $claims = new ControlHandshakeClaims(
            ControlHandshakeStage::Hello,
            'bedriox-gateway',
            'lobby',
            'primary',
            ControlHandshakeClaims::nonce(),
            null,
            1_000,
            1_015,
        );
        $secret = str_repeat('s', 32);
        $token = ControlHandshakeToken::issue($claims, $secret);

        $this->expectException(MalformedDataException::class);
        ControlHandshakeToken::verify(substr($token, 0, -1) . ($token[-1] === 'A' ? 'B' : 'A'), $secret);
    }

    public function testSeparatorFloodIsRejected(): void
    {
        $this->expectException(MalformedDataException::class);
        ControlHandshakeToken::verify(str_repeat('x.', 1_000), str_repeat('s', 32));
    }

    public function testEncryptedFramesAuthenticateMetadataAndPayload(): void
    {
        $key = random_bytes(32);
        $cipher = new ControlFrameCipher($key, 'gateway-to-server');
        $plain = new ControlFrame(
            ControlMessageType::PluginMessage,
            '00112233445566778899aabbccddeeff',
            42,
            '{"channel":"example:test","data":"hello"}',
        );

        $sealed = $cipher->seal($plain);

        self::assertTrue($sealed->encrypted());
        self::assertNotSame($plain->payload, $sealed->payload);
        self::assertEquals($plain, $cipher->open($sealed));

        $modified = new ControlFrame(
            ControlMessageType::PluginMessage,
            $sealed->requestId,
            $sealed->sequence,
            substr($sealed->payload, 0, -1) . ($sealed->payload[-1] ^ "\x01"),
            $sealed->flags,
        );
        try {
            $cipher->open($modified);
            self::fail('Modified Gateway control ciphertext was accepted.');
        } catch (CryptographicException) {
        }
    }

    public function testCipherDirectionIsAuthenticated(): void
    {
        $key = random_bytes(32);
        $frame = new ControlFrame(ControlMessageType::Ready, str_repeat('a', 32), 1, 'ready');
        $sealed = (new ControlFrameCipher($key, 'server-to-gateway'))->seal($frame);

        $this->expectException(CryptographicException::class);
        (new ControlFrameCipher($key, 'gateway-to-server'))->open($sealed);
    }
}
