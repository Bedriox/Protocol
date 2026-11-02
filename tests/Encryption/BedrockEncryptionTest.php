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
use Bedriox\Protocol\Encryption\BedrockEncryptedBatchCodec;
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
        $first = $encryptor->encryptBatch("\x00\x01\x02\x03");
        $second = $encryptor->encryptBatch('xyz');

        self::assertSame('4703d418a77657d6e7b813fb', bin2hex($first));
        self::assertSame('c99002e9d04f9f22475d45', bin2hex($second));

        $decryptor = new BedrockDecryptor($key);
        self::assertSame("\x00\x01\x02\x03", $decryptor->decryptBatch($first));
        self::assertSame('xyz', $decryptor->decryptBatch($second));
    }

    public function testEncryptedBatchHasIndependentVector(): void
    {
        $clearEnvelope = BedrockBatchCodec::encode(new BedrockBatch([
            new PacketFrame(new PacketHeader(1), "\xaa"),
            new PacketFrame(new PacketHeader(2), ''),
        ], CompressionMode::NegotiatedZlib), new BatchLimits());
        self::assertSame('0063625cc5c80400', bin2hex($clearEnvelope));
        $wire = BedrockEncryptedBatchCodec::encode($clearEnvelope, new BedrockEncryptor(self::key()));

        self::assertSame('4761b447002dc61b21f97cc9617ef6fd', bin2hex($wire));
        $decryptedEnvelope = BedrockEncryptedBatchCodec::decode($wire, new BedrockDecryptor(self::key()));
        self::assertSame($clearEnvelope, $decryptedEnvelope);
        self::assertCount(2, BedrockBatchCodec::decode(
            $decryptedEnvelope,
            CompressionMode::NegotiatedZlib,
            new BatchLimits(),
        )->packets);
    }

    public function testEncryptedBatchRejectsInvalidLengthsAndClosesInboundState(): void
    {
        $encryptor = new BedrockEncryptor(self::key());
        foreach ([''] as $invalid) {
            try {
                BedrockEncryptedBatchCodec::encode($invalid, $encryptor);
                self::fail('Invalid clear batch was accepted.');
            } catch (InvalidValueException) {
            }
        }
        self::assertSame(
            '4761b447002dc61b21f97cc9617ef6fd',
            bin2hex(BedrockEncryptedBatchCodec::encode(hex2bin('0063625cc5c80400') ?: '', $encryptor)),
        );

        $wire = hex2bin('4761b447002dc61b21f97cc9617ef6fd');
        self::assertIsString($wire);
        self::assertSame(
            '0063625cc5c80400',
            bin2hex(BedrockEncryptedBatchCodec::decode($wire, new BedrockDecryptor(self::key()))),
        );

        $truncated = new BedrockDecryptor(self::key());
        try {
            BedrockEncryptedBatchCodec::decode(str_repeat("\0", 8), $truncated);
            self::fail('Trailer-only encrypted batch was accepted.');
        } catch (MalformedDataException) {
        }
        $this->expectException(LogicException::class);
        BedrockEncryptedBatchCodec::decode($wire, $truncated);
    }

    public function testEncryptedBatchChecksLimitsBeforeSlicingAndFailsClosedInbound(): void
    {
        $encryptor = new BedrockEncryptor(self::key(), new EncryptionLimits(3));
        try {
            BedrockEncryptedBatchCodec::encode('1234', $encryptor);
            self::fail('Oversized clear batch was accepted.');
        } catch (InvalidValueException) {
        }
        self::assertNotSame('', BedrockEncryptedBatchCodec::encode('123', $encryptor));

        $decryptor = new BedrockDecryptor(self::key(), new EncryptionLimits(3));
        try {
            BedrockEncryptedBatchCodec::decode(str_repeat("\0", 12), $decryptor);
            self::fail('Oversized encrypted batch was accepted.');
        } catch (MalformedDataException) {
        }
        $this->expectException(LogicException::class);
        $decryptor->decryptBatch(str_repeat("\0", 11));
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
        $continuous->encryptBatch('first');
        $second = $continuous->encryptBatch('second');

        $reinitialized = new BedrockEncryptor(self::key());
        self::assertNotSame($reinitialized->encryptBatch('second'), $second);
    }

    public function testCorruptionFailsClosedWithoutReturningPlaintext(): void
    {
        $wire = (new BedrockEncryptor(self::key()))->encryptBatch('secret');
        $wire[3] = chr(ord($wire[3]) ^ 0x80);
        $decryptor = new BedrockDecryptor(self::key());
        try {
            $decryptor->decryptBatch($wire);
            self::fail('Corrupted encrypted payload returned plaintext.');
        } catch (MalformedDataException) {
        }

        $this->expectException(LogicException::class);
        $decryptor->decryptBatch($wire);
    }

    public function testReplayFailsAndClosesDirection(): void
    {
        $wire = (new BedrockEncryptor(self::key()))->encryptBatch('once');
        $decryptor = new BedrockDecryptor(self::key());
        self::assertSame('once', $decryptor->decryptBatch($wire));
        try {
            $decryptor->decryptBatch($wire);
            self::fail('Replayed encrypted payload was accepted.');
        } catch (MalformedDataException) {
        }
        $this->expectException(LogicException::class);
        $decryptor->decryptBatch($wire);
    }

    public function testReorderedPacketFailsClosed(): void
    {
        $encryptor = new BedrockEncryptor(self::key());
        $encryptor->encryptBatch('first');
        $second = $encryptor->encryptBatch('second');

        $decryptor = new BedrockDecryptor(self::key());
        $this->expectException(MalformedDataException::class);
        $decryptor->decryptBatch($second);
    }

    public function testIndependentDirectionsDoNotShareState(): void
    {
        $leftToRight = new BedrockEncryptor(self::key());
        $rightToLeft = new BedrockEncryptor(self::key());
        self::assertSame($leftToRight->encryptBatch('same'), $rightToLeft->encryptBatch('same'));

        $leftReceiver = new BedrockDecryptor(self::key());
        $rightReceiver = new BedrockDecryptor(self::key());
        self::assertSame('same', $leftReceiver->decryptBatch((new BedrockEncryptor(self::key()))->encryptBatch('same')));
        self::assertSame('same', $rightReceiver->decryptBatch((new BedrockEncryptor(self::key()))->encryptBatch('same')));
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
            $encryptor->encryptBatch('');
            self::fail('Empty compressed batch was accepted.');
        } catch (InvalidValueException) {
        }
        try {
            $encryptor->encryptBatch('four');
            self::fail('Oversized compressed batch was accepted.');
        } catch (InvalidValueException) {
        }
        self::assertNotSame('', $encryptor->encryptBatch('ok'));

        $this->expectException(InvalidValueException::class);
        new EncryptionLimits(4_194_305);
    }

    public function testMalformedLengthClosesDecryptor(): void
    {
        $decryptor = new BedrockDecryptor(self::key(), new EncryptionLimits(3));
        try {
            $decryptor->decryptBatch(str_repeat("\0", 8));
            self::fail('Trailer-only input was accepted.');
        } catch (MalformedDataException) {
        }
        $this->expectException(LogicException::class);
        $decryptor->decryptBatch(str_repeat("\0", 9));
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
        $encryptor->encryptBatch('closed');
    }

    private static function key(): string
    {
        $key = hex2bin(self::KEY_HEX);
        self::assertIsString($key);
        return $key;
    }
}
