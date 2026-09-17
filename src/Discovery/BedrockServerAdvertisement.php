<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Discovery;

use Bedriox\Protocol\Exception\InvalidValueException;
use Bedriox\Protocol\ProtocolVersion;

final readonly class BedrockServerAdvertisement
{
    public const string EDITION = 'MCPE';
    public const int MAXIMUM_ENCODED_BYTES = 352;
    public const int MAXIMUM_MOTD_BYTES = 128;
    public const int MAXIMUM_SUB_MOTD_BYTES = 128;
    public const int MAXIMUM_PLAYER_COUNT = 2_147_483_647;

    public string $motd;
    public string $subMotd;

    public function __construct(
        string $motd,
        public int $onlinePlayers,
        public int $maximumPlayers,
        public int $serverId,
        string $subMotd = 'Bedriox',
        public AdvertisedGameMode $gameMode = AdvertisedGameMode::Survival,
        public bool $nintendoLimited = false,
        public int $ipv4Port = 19_132,
        public int $ipv6Port = 19_133,
    ) {
        if ($this->onlinePlayers < 0 || $this->onlinePlayers > self::MAXIMUM_PLAYER_COUNT
            || $this->maximumPlayers < 0 || $this->maximumPlayers > self::MAXIMUM_PLAYER_COUNT) {
            throw new InvalidValueException('Advertised player counts must be between 0 and 2147483647.');
        }
        if ($this->onlinePlayers > $this->maximumPlayers) {
            throw new InvalidValueException('Advertised online players cannot exceed maximum players.');
        }
        if ($this->serverId < 0) {
            throw new InvalidValueException('Advertised server ID must be a nonnegative 63-bit integer.');
        }
        if (!self::isPort($this->ipv4Port) || !self::isPort($this->ipv6Port)) {
            throw new InvalidValueException('Advertised ports must be between 1 and 65535.');
        }

        $this->motd = self::sanitizeText($motd, self::MAXIMUM_MOTD_BYTES, 'MOTD');
        $this->subMotd = self::sanitizeText($subMotd, self::MAXIMUM_SUB_MOTD_BYTES, 'sub-MOTD');

        if (\strlen($this->encode()) > self::MAXIMUM_ENCODED_BYTES) {
            throw new InvalidValueException('Encoded Bedrock advertisement exceeds 352 bytes.');
        }
    }

    public function encode(): string
    {
        return implode(';', [
            self::EDITION,
            $this->motd,
            (string) ProtocolVersion::CURRENT,
            ProtocolVersion::GAME_VERSION,
            (string) $this->onlinePlayers,
            (string) $this->maximumPlayers,
            (string) $this->serverId,
            $this->subMotd,
            $this->gameMode->value,
            $this->nintendoLimited ? '0' : '1',
            (string) $this->ipv4Port,
            (string) $this->ipv6Port,
            '',
        ]);
    }

    private static function sanitizeText(string $value, int $maximumBytes, string $field): string
    {
        if (preg_match('//u', $value) !== 1) {
            throw new InvalidValueException("Advertised {$field} must be valid UTF-8.");
        }
        if (preg_match('/[;\p{C}]/u', $value) === 1) {
            throw new InvalidValueException("Advertised {$field} cannot contain separators or control characters.");
        }

        $sanitized = trim($value);
        $sanitized = preg_replace('/ {2,}/', ' ', $sanitized);
        if ($sanitized === null || $sanitized === '') {
            throw new InvalidValueException("Advertised {$field} cannot be empty.");
        }
        if (\strlen($sanitized) > $maximumBytes) {
            throw new InvalidValueException("Advertised {$field} exceeds {$maximumBytes} bytes.");
        }

        return $sanitized;
    }

    private static function isPort(int $port): bool
    {
        return $port >= 1 && $port <= 65_535;
    }
}
