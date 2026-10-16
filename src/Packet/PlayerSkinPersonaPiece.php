<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

/** Immutable bounded persona geometry component carried by a player skin. */
final readonly class PlayerSkinPersonaPiece
{
    public function __construct(
        public string $id,
        public PlayerSkinPersonaPieceType $type,
        public string $packId,
        public bool $default,
        public string $productId,
    ) {
        CodecSupport::validateString($id, 4_096, 'Persona piece ID');
        CodecSupport::uuidToWire($packId);
        CodecSupport::validateString($productId, 4_096, 'Persona piece product ID');
    }
}
