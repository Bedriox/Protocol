<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Batch;

/** Explicit compression framing used during RakNet 11 login negotiation. */
enum CompressionMode
{
    /** Pre-NetworkSettings RakNet 11 batch with no algorithm byte. */
    case Uncompressed;
    /** Legacy RFC 1950 zlib stream without an algorithm byte. */
    case Zlib;
    /** Post-negotiation 0xff algorithm byte followed by plain batch bytes. */
    case PrefixedNone;
    /** Post-negotiation zlib: 0x00 raw DEFLATE or 0xff plain according to the negotiated threshold. */
    case NegotiatedZlib;
}
