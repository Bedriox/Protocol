<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

final readonly class AudioContentRegistrationEntry
{
    private const int MAX_CONTENT_BYTES = 1_048_576;

    public function __construct(
        public string $audioContentId,
        public string $sharedMetadata,
        public string $serverContent,
        public string $playbackContent,
    ) {
        CodecSupport::validateString($audioContentId, CodecSupport::MAX_SHORT_STRING_BYTES, 'Audio content ID');
        CodecSupport::validateString($sharedMetadata, self::MAX_CONTENT_BYTES, 'Audio shared metadata');
        CodecSupport::validateString($serverContent, self::MAX_CONTENT_BYTES, 'Audio server content');
        CodecSupport::validateString($playbackContent, self::MAX_CONTENT_BYTES, 'Audio playback content');
    }
}
