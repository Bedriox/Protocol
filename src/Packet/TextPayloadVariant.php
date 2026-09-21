<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

/** Protocol-2193 text payload union discriminators. */
enum TextPayloadVariant: int
{
    case MessageOnly = 0;
    case AuthorAndMessage = 1;
    case MessageAndParameters = 2;
}
