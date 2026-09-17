<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

use Bedriox\Protocol\Exception\InvalidValueException;

final readonly class GameRule
{
    public const int BOOLEAN = 1;
    public const int INTEGER = 2;
    public const int FLOAT = 3;

    public function __construct(
        public string $name,
        public int $type,
        public bool|int|float $value,
        public bool $editable = false,
    ) {
        if (preg_match('/^[a-z][a-z0-9]{0,63}$/D', $name) !== 1
            || !in_array($type, [self::BOOLEAN, self::INTEGER, self::FLOAT], true)
            || ($type === self::BOOLEAN) !== is_bool($value)
            || ($type === self::INTEGER) !== is_int($value)
            || ($type === self::FLOAT && (!is_float($value) || !is_finite($value)))) {
            throw new InvalidValueException('Game rule name, type, or value is invalid.');
        }
    }
}
