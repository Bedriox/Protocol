<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

use Bedriox\Protocol\Exception\InvalidValueException;

/** One bounded StartGame experiment toggle. */
final readonly class ExperimentData
{
    private const int MAX_NAME_BYTES = 256;

    public function __construct(
        public string $name,
        public bool $enabled,
    ) {
        CodecSupport::validateString($name, self::MAX_NAME_BYTES, 'Experiment name');
        if ($name === '') {
            throw new InvalidValueException('Experiment name cannot be empty.');
        }
    }

    /** @return list<self> */
    public static function requiredForDataDrivenBlocks(): array
    {
        return [
            new self('data_driven_items', true),
            new self('upcoming_creator_features', true),
            new self('experimental_molang_features', true),
        ];
    }
}
