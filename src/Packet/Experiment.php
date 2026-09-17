<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

final readonly class Experiment
{
    public function __construct(public string $name, public bool $enabled)
    {
        CodecSupport::validateString($name, CodecSupport::MAX_SHORT_STRING_BYTES, 'Experiment name');
    }
}
