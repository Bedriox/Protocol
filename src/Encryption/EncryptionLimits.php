<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Encryption;

use Bedriox\Protocol\Exception\InvalidValueException;

final readonly class EncryptionLimits
{
    public const int MAXIMUM_ALLOWED_COMPRESSED_BATCH_BYTES = 4_194_304;

    public function __construct(public int $maximumCompressedBatchBytes = 1_048_576)
    {
        if ($maximumCompressedBatchBytes < 1 || $maximumCompressedBatchBytes > self::MAXIMUM_ALLOWED_COMPRESSED_BATCH_BYTES) {
            throw new InvalidValueException('Encrypted batch limit must be between 1 and 4194304 bytes.');
        }
    }
}
