<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

use Bedriox\Protocol\Exception\InvalidValueException;

/** Bounded current SoundEvent serialize name. */
final readonly class LevelSoundEventName
{
    public const string HIT = 'hit';
    public const string BREAK = 'break';
    public const string PLACE = 'place';
    public const string GLASS = 'glass';
    public const string POTION_BREWED = 'potion.brewed';
    public const string EXPLODE = 'explode';

    public function __construct(public string $value)
    {
        CodecSupport::validateString($value, CodecSupport::MAX_SHORT_STRING_BYTES, 'Level sound event name');
        if ($value === '') {
            throw new InvalidValueException('Level sound event name cannot be empty.');
        }
    }

    public static function hit(): self { return new self(self::HIT); }
    public static function break(): self { return new self(self::BREAK); }
    public static function place(): self { return new self(self::PLACE); }
    public static function glass(): self { return new self(self::GLASS); }
    public static function potionBrewed(): self { return new self(self::POTION_BREWED); }
    public static function explode(): self { return new self(self::EXPLODE); }
}
