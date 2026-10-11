<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

/** Current-protocol metadata for a lightweight falling-block-backed text display. */
final class TextDisplayActorMetadata
{
    public const string IDENTIFIER = 'minecraft:falling_block';

    private const int FLAGS = 0;
    private const int VARIANT = 2;
    private const int NAMETAG = 4;
    private const int SCALE = 38;
    private const int BOUNDING_BOX_WIDTH = 53;
    private const int BOUNDING_BOX_HEIGHT = 54;
    private const int ALWAYS_SHOW_NAMETAG = 81;

    private function __construct()
    {
    }

    /** @return list<ActorMetadata> */
    public static function baseline(string $text, int $airNetworkId): array
    {
        return [
            ActorMetadata::long(
                self::FLAGS,
                ActorFlag::combine(ActorFlag::CanShowName, ActorFlag::NoAi),
            ),
            ActorMetadata::int(self::VARIANT, $airNetworkId),
            ActorMetadata::string(self::NAMETAG, $text),
            ActorMetadata::float(self::SCALE, self::float32(0.01)),
            ActorMetadata::float(self::BOUNDING_BOX_WIDTH, 0.0),
            ActorMetadata::float(self::BOUNDING_BOX_HEIGHT, 0.0),
            ActorMetadata::byte(self::ALWAYS_SHOW_NAMETAG, 1),
        ];
    }

    public static function text(string $text): ActorMetadata
    {
        return ActorMetadata::string(self::NAMETAG, $text);
    }

    private static function float32(float $value): float
    {
        $decoded = unpack('gvalue', pack('g', $value));
        if ($decoded === false || !is_float($decoded['value'])) {
            throw new \LogicException('Unable to normalize text-display metadata float.');
        }

        return $decoded['value'];
    }
}
