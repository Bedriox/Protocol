# Bedriox Protocol

Bedriox Protocol is the bounded Minecraft: Bedrock Edition wire-protocol library for [Bedriox](https://bedriox.com). It is an early foundation project and is not yet usable as a complete client or server.

## Responsibilities

- Binary primitives and bounded codecs.
- Packet definitions and a registry for the explicitly supported Bedrock release.
- Bedrock packet batching, compression, encryption framing, and NBT when implemented.
- Stable boundaries that do not depend on server, player, or world objects.

RakNet transport, gameplay, authentication policy, and copyrighted game data are out of scope.

## Install and use

The package is not published yet. During private development, add the repository as a Composer VCS repository and require `bedriox/protocol`.

The codec requires 64-bit PHP 8.4 through 8.x.

```php
use Bedriox\Protocol\Codec\UnsignedVarInt;

$bytes = UnsignedVarInt::encode(128);
$decoded = UnsignedVarInt::decode($bytes);
```

For stateful parsing without mutable cursors, use the bounded reader:

```php
use Bedriox\Protocol\Codec\ByteBufferReader;

$reader = ByteBufferReader::fromString($networkBytes, maximumBytes: 1_048_576);
$packetId = $reader->readUnsignedVarInt();
$payloadReader = $packetId->reader; // the original reader remains unchanged
```

See the [codebase map](docs/codebase-map.md), [packet contract](docs/packet-contract.md), [change-safety guide](docs/change-safety.md), [packet reference](docs/bedrock-packets.md), [codec reference](docs/codecs.md), [usage guide](docs/usage.md), [architecture](docs/architecture.md), [development](docs/development.md), [compatibility](docs/compatibility.md), and [troubleshooting](docs/troubleshooting.md) before integrating it.

## Status and license

The sole wire target is Minecraft 1.26.50 / protocol 2193; Minecraft 1.26.51 is qualified as a same-protocol retail client. The library provides a bounded gameplay/bootstrap subset and dataset-backed fixed-flat chunk generation. Packet names remain unversioned because upgrades replace this single current target rather than creating parallel protocol APIs. Other protocol numbers fail closed. This is not a claim of complete gameplay interoperability. Original source code is licensed under GPL-3.0-only. Required third-party legal attribution is recorded in `NOTICE` or `THIRD_PARTY_NOTICES.md` when applicable.
