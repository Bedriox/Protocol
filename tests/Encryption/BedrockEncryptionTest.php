<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Tests\Encryption;

use LogicException;
use OverflowException;
use PHPUnit\Framework\TestCase;
use Bedriox\Protocol\Batch\BatchLimits;
use Bedriox\Protocol\Batch\BedrockBatch;
use Bedriox\Protocol\Batch\BedrockBatchCodec;
use Bedriox\Protocol\Batch\CompressionMode;
use Bedriox\Protocol\Encryption\Aes256CtrStream;
use Bedriox\Protocol\Encryption\EncryptionLimits;
use Bedriox\Protocol\Encryption\PacketCounter;
use Bedriox\Protocol\Encryption\BedrockDecryptor;
use Bedriox\Protocol\Encryption\BedrockEncryptedEnvelopeCodec;
use Bedriox\Protocol\Encryption\BedrockEncryptor;
use Bedriox\Protocol\Exception\InvalidValueException;
use Bedriox\Protocol\Exception\MalformedDataException;
use Bedriox\Protocol\Packet\PacketFrame;
use Bedriox\Protocol\Packet\PacketHeader;

final class BedrockEncryptionTest extends TestCase
{
    private const string KEY_HEX = '000102030405060708090a0b0c0d0e0f101112131415161718191a1b1c1d1e1f';

    public function testIndependentKnownAnswerVectorAndContinuousSecondPacket(): void
    {
        $key = self::key();
        $encryptor = new BedrockEncryptor($key);
        $first = $encryptor->encryptEnvelope("\xfe\x00\x01\x02\x03");
        $second = $encryptor->encryptEnvelope("\xfexyz");

        self::assertSame('fe4703d418a77657d6e7b813fb', bin2hex($first));
        self::assertSame('fec99002e9d04f9f22475d45', bin2hex($second));

        $decryptor = new BedrockDecryptor($key);
        self::assertSame("\xfe\x00\x01\x02\x03", $decryptor->decryptEnvelope($first));
        self::assertSame("\xfexyz", $decryptor->decryptEnvelope($second));
    }

    public function testOuterEnvelopeLeavesMarkerClearWithIndependentVector(): void
    {
        $clearEnvelope = BedrockBatchCodec::encode(new BedrockBatch([
            new PacketFrame(new PacketHeader(1), "\xaa"),
            new PacketFrame(new PacketHeader(2), ''),
        ], CompressionMode::NegotiatedZlib), new BatchLimits());
        self::assertSame('fe0063625cc5c80400', bin2hex($clearEnvelope));
        $wire = BedrockEncryptedEnvelopeCodec::encode($clearEnvelope, new BedrockEncryptor(self::key()));

        self::assertSame('fe4761b447002dc61b21f97cc9617ef6fd', bin2hex($wire));
        self::assertSame("\xfe", $wire[0]);
        $decryptedEnvelope = BedrockEncryptedEnvelopeCodec::decode($wire, new BedrockDecryptor(self::key()));
        self::assertSame($clearEnvelope, $decryptedEnvelope);
        self::assertCount(2, BedrockBatchCodec::decode(
            $decryptedEnvelope,
            CompressionMode::NegotiatedZlib,
            new BatchLimits(),
        )->packets);
    }

    public function testOuterEnvelopeRejectsMarkerMisuseWithoutConsumingCipherState(): void
    {
        $encryptor = new BedrockEncryptor(self::key());
        foreach (['', "\xfdplain", "\xfe"] as $invalid) {
            try {
                BedrockEncryptedEnvelopeCodec::encode($invalid, $encryptor);
                self::fail('Invalid clear envelope was accepted.');
            } catch (InvalidValueException) {
            }
        }
        self::assertSame(
            'fe4761b447002dc61b21f97cc9617ef6fd',
            bin2hex(BedrockEncryptedEnvelopeCodec::encode(hex2bin('fe0063625cc5c80400') ?: '', $encryptor)),
        );

        $decryptor = new BedrockDecryptor(self::key());
        foreach (['', "\xfd"] as $invalid) {
            try {
                BedrockEncryptedEnvelopeCodec::decode($invalid, $decryptor);
                self::fail('Invalid encrypted envelope was accepted.');
            } catch (MalformedDataException) {
            }
        }
        $wire = hex2bin('fe4761b447002dc61b21f97cc9617ef6fd');
        self::assertIsString($wire);
        self::assertSame(
            'fe0063625cc5c80400',
            bin2hex(BedrockEncryptedEnvelopeCodec::decode($wire, $decryptor)),
        );

        $truncated = new BedrockDecryptor(self::key());
        try {
            BedrockEncryptedEnvelopeCodec::decode("\xfe", $truncated);
            self::fail('Marker-only encrypted envelope was accepted.');
        } catch (MalformedDataException) {
        }
        $this->expectException(LogicException::class);
        BedrockEncryptedEnvelopeCodec::decode($wire, $truncated);
    }

    public function testOuterEnvelopeChecksLimitsBeforeSlicingAndFailsClosedInbound(): void
    {
        $encryptor = new BedrockEncryptor(self::key(), new EncryptionLimits(3));
        try {
            BedrockEncryptedEnvelopeCodec::encode("\xfe1234", $encryptor);
            self::fail('Oversized clear envelope was accepted.');
        } catch (InvalidValueException) {
        }
        self::assertNotSame('', BedrockEncryptedEnvelopeCodec::encode("\xfe123", $encryptor));

        $decryptor = new BedrockDecryptor(self::key(), new EncryptionLimits(3));
        try {
            BedrockEncryptedEnvelopeCodec::decode("\xfe" . str_repeat("\0", 12), $decryptor);
            self::fail('Oversized encrypted envelope was accepted.');
        } catch (MalformedDataException) {
        }
        $this->expectException(LogicException::class);
        $decryptor->decryptEnvelope("\xfe" . str_repeat("\0", 11));
    }

    public function testCtrStateSurvivesArbitraryNonBlockUpdateBoundaries(): void
    {
        $input = str_repeat('non-block-boundary-', 9);
        $whole = new Aes256CtrStream(self::key());
        $expected = $whole->update($input);

        $split = new Aes256CtrStream(self::key());
        $actual = $split->update(substr($input, 0, 1))
            . $split->update(substr($input, 1, 14))
            . $split->update(substr($input, 15, 17))
            . $split->update(substr($input, 32));
        self::assertSame($expected, $actual);
    }

    public function testContinuousStreamIsNotPerPacketReinitialization(): void
    {
        $continuous = new BedrockEncryptor(self::key());
        $continuous->encryptEnvelope("\xfefirst");
        $second = $continuous->encryptEnvelope("\xfesecond");

        $reinitialized = new BedrockEncryptor(self::key());
        self::assertNotSame($reinitialized->encryptEnvelope("\xfesecond"), $second);
    }

    public function testCorruptionFailsClosedWithoutReturningPlaintext(): void
    {
        $wire = (new BedrockEncryptor(self::key()))->encryptEnvelope("\xfesecret");
        $wire[3] = chr(ord($wire[3]) ^ 0x80);
        $decryptor = new BedrockDecryptor(self::key());
        try {
            $decryptor->decryptEnvelope($wire);
            self::fail('Corrupted encrypted payload returned plaintext.');
        } catch (MalformedDataException) {
        }

        $this->expectException(LogicException::class);
        $decryptor->decryptEnvelope($wire);
    }

    public function testReplayFailsAndClosesDirection(): void
    {
        $wire = (new BedrockEncryptor(self::key()))->encryptEnvelope("\xfeonce");
        $decryptor = new BedrockDecryptor(self::key());
        self::assertSame("\xfeonce", $decryptor->decryptEnvelope($wire));
        try {
            $decryptor->decryptEnvelope($wire);
            self::fail('Replayed encrypted payload was accepted.');
        } catch (MalformedDataException) {
        }
        $this->expectException(LogicException::class);
        $decryptor->decryptEnvelope($wire);
    }

    public function testReorderedPacketFailsClosed(): void
    {
        $encryptor = new BedrockEncryptor(self::key());
        $encryptor->encryptEnvelope("\xfefirst");
        $second = $encryptor->encryptEnvelope("\xfesecond");

        $decryptor = new BedrockDecryptor(self::key());
        $this->expectException(MalformedDataException::class);
        $decryptor->decryptEnvelope($second);
    }

    public function testIndependentDirectionsDoNotShareState(): void
    {
        $leftToRight = new BedrockEncryptor(self::key());
        $rightToLeft = new BedrockEncryptor(self::key());
        self::assertSame($leftToRight->encryptEnvelope("\xfesame"), $rightToLeft->encryptEnvelope("\xfesame"));

        $leftReceiver = new BedrockDecryptor(self::key());
        $rightReceiver = new BedrockDecryptor(self::key());
        self::assertSame("\xfesame", $leftReceiver->decryptEnvelope((new BedrockEncryptor(self::key()))->encryptEnvelope("\xfesame")));
        self::assertSame("\xfesame", $rightReceiver->decryptEnvelope((new BedrockEncryptor(self::key()))->encryptEnvelope("\xfesame")));
    }

    public function testKeyLengthAndBounds(): void
    {
        foreach (['', str_repeat('k', 31), str_repeat('k', 33)] as $key) {
            try {
                new BedrockEncryptor($key);
                self::fail('Invalid AES-256 key length was accepted.');
            } catch (InvalidValueException) {
            }
        }
        $encryptor = new BedrockEncryptor(self::key(), new EncryptionLimits(3));
        try {
            $encryptor->encryptEnvelope('');
            self::fail('Empty compressed batch was accepted.');
        } catch (InvalidValueException) {
        }
        try {
            $encryptor->encryptEnvelope("\xfefour");
            self::fail('Oversized compressed batch was accepted.');
        } catch (InvalidValueException) {
        }
        self::assertNotSame('', $encryptor->encryptEnvelope("\xfeok"));

        $this->expectException(InvalidValueException::class);
        new EncryptionLimits(4_194_305);
    }

    public function testMalformedLengthClosesDecryptor(): void
    {
        $decryptor = new BedrockDecryptor(self::key(), new EncryptionLimits(3));
        try {
            $decryptor->decryptEnvelope("\xfe" . str_repeat("\0", 8));
            self::fail('Trailer-only input was accepted.');
        } catch (MalformedDataException) {
        }
        $this->expectException(LogicException::class);
        $decryptor->decryptEnvelope("\xfe" . str_repeat("\0", 9));
    }

    public function testPacketCounterRolloverAndOverflow(): void
    {
        $rollover = new PacketCounter(0, 0xffffffff);
        self::assertSame('ffffffff00000000', bin2hex($rollover->consumeLittleEndian()));
        self::assertSame('0000000001000000', bin2hex($rollover->consumeLittleEndian()));

        $maximum = new PacketCounter(0xffffffff, 0xffffffff);
        self::assertSame('ffffffffffffffff', bin2hex($maximum->consumeLittleEndian()));
        $this->expectException(OverflowException::class);
        $maximum->consumeLittleEndian();
    }

    public function testExplicitCloseIsIdempotentAndTerminal(): void
    {
        $encryptor = new BedrockEncryptor(self::key());
        $encryptor->close();
        $encryptor->close();
        $this->expectException(LogicException::class);
        $encryptor->encryptEnvelope("\xfeclosed");
    }

    private static function key(): string
    {
        $key = hex2bin(self::KEY_HEX);
        self::assertIsString($key);
        return $key;
    }
}
