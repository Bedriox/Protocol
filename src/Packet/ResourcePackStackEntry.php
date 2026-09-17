<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

final readonly class ResourcePackStackEntry
{
    public function __construct(public string $packId, public string $packVersion, public string $subPackName)
    {
        CodecSupport::validateString($packId, CodecSupport::MAX_SHORT_STRING_BYTES, 'Pack ID');
        CodecSupport::validateString($packVersion, CodecSupport::MAX_SHORT_STRING_BYTES, 'Pack version');
        CodecSupport::validateString($subPackName, CodecSupport::MAX_SHORT_STRING_BYTES, 'Sub-pack name');
    }
}
