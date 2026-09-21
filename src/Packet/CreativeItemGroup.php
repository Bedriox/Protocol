<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

final readonly class CreativeItemGroup
{
    public function __construct(
        public CreativeItemCategory $category,
        public string $name,
        public InventoryItemStack $icon,
    ) {
        CodecSupport::validateString($name, CodecSupport::MAX_SHORT_STRING_BYTES, 'Creative group name');
        CreativeItemStackWireCodec::validate($icon);
    }
}
