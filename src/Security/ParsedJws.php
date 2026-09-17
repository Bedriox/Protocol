<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Security;

final readonly class ParsedJws
{
    /**
     * @param array<string, mixed> $header
     * @param array<string, mixed> $payload
     */
    public function __construct(
        public array $header,
        public array $payload,
        public string $signature,
        public string $signingInput,
    ) {
    }
}
