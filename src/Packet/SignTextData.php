<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

use Bedriox\Protocol\Exception\InvalidValueException;

/** Strictly bounded text state for one side of a Bedrock sign block actor. */
final readonly class SignTextData
{
    public const int MAXIMUM_TEXT_BYTES = 1_023;
    public const int MAXIMUM_OWNER_BYTES = 128;

    public function __construct(
        public string $text,
        public int $color,
        public bool $glowing,
        public bool $persistFormatting = true,
        public string $owner = '',
    ) {
        if (strlen($text) > self::MAXIMUM_TEXT_BYTES || preg_match('//u', $text) !== 1) {
            throw new InvalidValueException('Sign text is invalid or oversized.');
        }
        if (strlen($owner) > self::MAXIMUM_OWNER_BYTES || preg_match('//u', $owner) !== 1) {
            throw new InvalidValueException('Sign text owner is invalid or oversized.');
        }
        if ($color < -0x80000000 || $color > 0x7fffffff) {
            throw new InvalidValueException('Sign text color must fit a signed 32-bit integer.');
        }
    }
}
