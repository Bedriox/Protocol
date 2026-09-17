<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Security;

interface EphemeralKeyFactory
{
    public function generate() : P384KeyPair;
}
