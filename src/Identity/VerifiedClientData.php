<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Identity;

use Bedriox\Protocol\Value\DeviceOs;

final readonly class VerifiedClientData
{
    /** @param list<VerifiedAnimation> $animations */
    public function __construct(
        public int $skinWidth,
        public int $skinHeight,
        public string $skin,
        public int $capeWidth,
        public int $capeHeight,
        public string $cape,
        public string $geometryJson,
        public array $animations,
        public string $skinId = '',
        public string $playFabId = '',
        public string $skinResourcePatchJson = '',
        public string $geometryEngineVersion = '',
        public string $animationDataJson = '',
        public string $capeId = '',
        public string $fullSkinId = '',
        public string $armSize = '',
        public string $skinColor = '',
        public bool $premium = false,
        public bool $persona = false,
        public bool $capeOnClassic = false,
        public bool $primaryUser = false,
        public bool $overridingPlayerAppearance = false,
        public string $profileHash = '',
        public DeviceOs $deviceOs = DeviceOs::Unknown,
    ) {
    }
}
