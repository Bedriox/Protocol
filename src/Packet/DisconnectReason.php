<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

/** Bedrock ordinal bounds and commonly emitted reasons. */
final class DisconnectReason
{
    public const int UNKNOWN = 0;
    public const int VERSION_MISMATCH = 8;
    public const int SERVER_FULL = 25;
    public const int OUTDATED_SERVER = 34;
    public const int OUTDATED_CLIENT = 35;
    public const int DISCONNECTED = 41;
    public const int NOT_AUTHENTICATED = 46;
    public const int KICKED = 55;
    public const int RESOURCE_PACK_PROBLEM = 58;
    public const int BAD_PACKET = 90;
    public const int NONCE_MISSING = 136;
    public const int NONCE_NOT_FOUND = 137;
    public const int NONCE_EXPIRED = 138;
    public const int NONCE_NOT_VALID = 139;
    public const int MIN = self::UNKNOWN;
    public const int MAX = self::NONCE_NOT_VALID;
}
