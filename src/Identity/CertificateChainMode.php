<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Identity;

enum CertificateChainMode
{
    case OnlineLegacy;
    case SelfSignedExplicit;
}
