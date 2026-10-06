<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

use Bedriox\Protocol\Exception\InvalidValueException;

/** Current-protocol Shulker attachment and shell-opening presentation metadata. */
final class ShulkerActorMetadata
{
    public const int PEEK_AMOUNT = 64;
    public const int ATTACH_FACE = 65;
    public const int ATTACHED = 66;

    private function __construct()
    {
    }

    /** @return list<ActorMetadata> */
    public static function presentation(
        int $peekAmount,
        ShulkerAttachmentFace $attachmentFace,
        bool $attached = true,
    ): array
    {
        if ($peekAmount < 0 || $peekAmount > 100) {
            throw new InvalidValueException('Shulker peek amount must be between zero and 100.');
        }

        return [
            ActorMetadata::int(self::PEEK_AMOUNT, $peekAmount),
            ActorMetadata::byte(self::ATTACH_FACE, $attachmentFace->value),
            ActorMetadata::short(self::ATTACHED, $attached ? 1 : 0),
        ];
    }
}
