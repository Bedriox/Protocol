<?php

declare(strict_types=1);

namespace Bedriox\Protocol;

use InvalidArgumentException;

final readonly class ProtocolVersion
{
    public const int CURRENT = 2193;
    public const string GAME_VERSION = '1.26.50';

    /** @var list<int> */
    public const array SUPPORTED = [self::CURRENT];

    /** @var array<int, string> */
    private const array GAME_VERSIONS = [
        self::CURRENT => self::GAME_VERSION,
    ];

    public function __construct(public int $id)
    {
        if ($id < 1) {
            throw new InvalidArgumentException('A protocol version must be a positive integer.');
        }
    }

    public function equals(self $other): bool
    {
        return $this->id === $other->id;
    }

    public static function supports(int $id): bool
    {
        return isset(self::GAME_VERSIONS[$id]);
    }

    public static function gameVersion(int $id): string
    {
        return self::GAME_VERSIONS[$id] ?? throw new InvalidArgumentException('Unsupported protocol version.');
    }
}
