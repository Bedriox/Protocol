<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

use Bedriox\Protocol\Exception\InvalidValueException;

/** Current-protocol metadata for an invisible, client-targetable interaction actor. */
final class InteractionActorMetadata
{
    public const string IDENTIFIER = 'minecraft:armor_stand';
    public const float DEFAULT_WIDTH = 2.0;
    public const float DEFAULT_HEIGHT = 1.0;
    public const float MAXIMUM_DIMENSION = 64.0;

    private const int FLAGS = 0;
    private const int BOUNDING_BOX_WIDTH = 53;
    private const int BOUNDING_BOX_HEIGHT = 54;

    private function __construct()
    {
    }

    /** @return list<ActorMetadata> */
    public static function baseline(
        float $width = self::DEFAULT_WIDTH,
        float $height = self::DEFAULT_HEIGHT,
    ): array {
        $width = self::validatedDimension($width, 'width');
        $height = self::validatedDimension($height, 'height');

        return [
            ActorMetadata::long(self::FLAGS, ActorFlag::combine(ActorFlag::Invisible, ActorFlag::NoAi)),
            ActorMetadata::float(self::BOUNDING_BOX_WIDTH, $width),
            ActorMetadata::float(self::BOUNDING_BOX_HEIGHT, $height),
        ];
    }

    /** @return list<ActorMetadata> */
    public static function size(float $width, float $height): array
    {
        $width = self::validatedDimension($width, 'width');
        $height = self::validatedDimension($height, 'height');

        return [
            ActorMetadata::float(self::BOUNDING_BOX_WIDTH, $width),
            ActorMetadata::float(self::BOUNDING_BOX_HEIGHT, $height),
        ];
    }

    private static function validatedDimension(float $value, string $field): float
    {
        if (!is_finite($value) || $value <= 0.0 || $value > self::MAXIMUM_DIMENSION) {
            throw new InvalidValueException("Interaction actor {$field} is outside its supported range.");
        }

        $decoded = unpack('gvalue', pack('g', $value));
        if ($decoded === false || !is_float($decoded['value']) || $decoded['value'] <= 0.0) {
            throw new InvalidValueException("Interaction actor {$field} is not a positive 32-bit float.");
        }

        return $decoded['value'];
    }
}
