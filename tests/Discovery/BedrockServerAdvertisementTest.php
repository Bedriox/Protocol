<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Tests\Discovery;

use Closure;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Bedriox\Protocol\Discovery\AdvertisedGameMode;
use Bedriox\Protocol\Discovery\BedrockServerAdvertisement;
use Bedriox\Protocol\Exception\InvalidValueException;

final class BedrockServerAdvertisementTest extends TestCase
{
    public function testEncodesIndependentLiteralProtocol2193Vector(): void
    {
        $advertisement = new BedrockServerAdvertisement(
            motd: 'Bedriox',
            onlinePlayers: 3,
            maximumPlayers: 20,
            serverId: 1_234,
            subMotd: 'Fast PHP',
            gameMode: AdvertisedGameMode::Survival,
            nintendoLimited: false,
            ipv4Port: 19_132,
            ipv6Port: 19_133,
        );

        self::assertSame(
            'MCPE;Bedriox;2193;1.26.50;3;20;1234;Fast PHP;Survival;1;19132;19133;',
            $advertisement->encode(),
        );
        self::assertLessThanOrEqual(BedrockServerAdvertisement::MAXIMUM_ENCODED_BYTES, \strlen($advertisement->encode()));
    }

    public function testNintendoLimitedUsesOnlyTheVerifiedInvertedBooleanValues(): void
    {
        $limited = new BedrockServerAdvertisement('Bedriox', 0, 20, 1, nintendoLimited: true);
        $advertised = new BedrockServerAdvertisement('Bedriox', 0, 20, 1, nintendoLimited: false);

        self::assertSame('0', explode(';', $limited->encode())[9]);
        self::assertSame('1', explode(';', $advertised->encode())[9]);
    }

    public function testTextIsTrimmedAndRepeatedSpacesAreCollapsed(): void
    {
        $advertisement = new BedrockServerAdvertisement('  Bedriox   Server  ', 0, 1, 9, '  Fast   PHP  ');

        self::assertSame('Bedriox Server', $advertisement->motd);
        self::assertSame('Fast PHP', $advertisement->subMotd);
    }

    public function testTextLimitIsMeasuredInUtf8Bytes(): void
    {
        $advertisement = new BedrockServerAdvertisement(str_repeat("\u{00e9}", 64), 0, 1, 9);

        self::assertSame(128, \strlen($advertisement->motd));
    }

    public function testOnlyQualifiedGameModeHasALiteralSemanticLabel(): void
    {
        self::assertSame(['Survival'], array_column(AdvertisedGameMode::cases(), 'value'));
    }

    /** @param Closure(): BedrockServerAdvertisement $create */
    #[DataProvider('invalidAdvertisementProvider')]
    public function testRejectsInvalidAdvertisement(Closure $create, string $message): void
    {
        $this->expectException(InvalidValueException::class);
        $this->expectExceptionMessage($message);
        $create();
    }

    /** @return iterable<string, array{Closure(): BedrockServerAdvertisement, string}> */
    public static function invalidAdvertisementProvider(): iterable
    {
        yield 'negative online count' => [static fn (): BedrockServerAdvertisement => new BedrockServerAdvertisement('Bedriox', -1, 1, 1), 'player counts'];
        yield 'negative maximum count' => [static fn (): BedrockServerAdvertisement => new BedrockServerAdvertisement('Bedriox', 0, -1, 1), 'player counts'];
        yield 'count above signed 32-bit' => [static fn (): BedrockServerAdvertisement => new BedrockServerAdvertisement('Bedriox', 0, 2_147_483_648, 1), 'player counts'];
        yield 'online above maximum' => [static fn (): BedrockServerAdvertisement => new BedrockServerAdvertisement('Bedriox', 2, 1, 1), 'cannot exceed'];
        yield 'negative server ID' => [static fn (): BedrockServerAdvertisement => new BedrockServerAdvertisement('Bedriox', 0, 1, -1), 'server ID'];
        yield 'zero IPv4 port' => [static fn (): BedrockServerAdvertisement => new BedrockServerAdvertisement('Bedriox', 0, 1, 1, ipv4Port: 0), 'ports'];
        yield 'oversized IPv6 port' => [static fn (): BedrockServerAdvertisement => new BedrockServerAdvertisement('Bedriox', 0, 1, 1, ipv6Port: 65_536), 'ports'];
        yield 'invalid UTF-8' => [static fn (): BedrockServerAdvertisement => new BedrockServerAdvertisement("broken\xc3\x28", 0, 1, 1), 'UTF-8'];
        yield 'separator injection' => [static fn (): BedrockServerAdvertisement => new BedrockServerAdvertisement('Veno;PE', 0, 1, 1), 'separators'];
        yield 'control injection' => [static fn (): BedrockServerAdvertisement => new BedrockServerAdvertisement("Veno\nPE", 0, 1, 1), 'control'];
        yield 'unicode control injection' => [static fn (): BedrockServerAdvertisement => new BedrockServerAdvertisement("Veno\u{0085}PE", 0, 1, 1), 'control'];
        yield 'empty after trim' => [static fn (): BedrockServerAdvertisement => new BedrockServerAdvertisement('   ', 0, 1, 1), 'empty'];
        yield 'MOTD byte limit' => [static fn (): BedrockServerAdvertisement => new BedrockServerAdvertisement(str_repeat("\u{00e9}", 65), 0, 1, 1), '128 bytes'];
        yield 'sub-MOTD byte limit' => [static fn (): BedrockServerAdvertisement => new BedrockServerAdvertisement('Bedriox', 0, 1, 1, str_repeat('x', 129)), '128 bytes'];
    }

    public function testMaximumBoundsRemainInsideTransportContract(): void
    {
        $advertisement = new BedrockServerAdvertisement(
            str_repeat('m', BedrockServerAdvertisement::MAXIMUM_MOTD_BYTES),
            BedrockServerAdvertisement::MAXIMUM_PLAYER_COUNT,
            BedrockServerAdvertisement::MAXIMUM_PLAYER_COUNT,
            PHP_INT_MAX,
            str_repeat('s', BedrockServerAdvertisement::MAXIMUM_SUB_MOTD_BYTES),
            AdvertisedGameMode::Survival,
            true,
            65_535,
            65_535,
        );

        self::assertSame(341, \strlen($advertisement->encode()));
        self::assertLessThanOrEqual(BedrockServerAdvertisement::MAXIMUM_ENCODED_BYTES, \strlen($advertisement->encode()));
        self::assertStringEndsWith(';', $advertisement->encode());
        self::assertCount(13, explode(';', $advertisement->encode()));
    }
}
