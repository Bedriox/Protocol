# Usage

Bedriox/Protocol currently exposes bounded binary primitives; it does not yet implement a complete Bedrock packet set.

```php
use Bedriox\Protocol\Codec\ByteBufferReader;
use Bedriox\Protocol\Codec\ByteBufferWriter;

$writer = ByteBufferWriter::withCapacity(64)
    ->writeUnsignedVarInt(300)
    ->writeString('Bedriox', maximumBytes: 32);

$reader = ByteBufferReader::fromString($writer->toString(), maximumBytes: 64);
$packetId = $reader->readUnsignedVarInt();
$name = $packetId->reader->readString(maximumBytes: 32);
```

Readers and writers are immutable: every operation returns a new reader/writer state. Always choose limits from the surrounding protocol field or session policy rather than using an unbounded application default.

Bedrock batch framing is explicit about the negotiated compression phase:

```php
use Bedriox\Protocol\Batch\BatchLimits;
use Bedriox\Protocol\Batch\BedrockBatchCodec;
use Bedriox\Protocol\Batch\CompressionMode;

$limits = new BatchLimits();
$batch = BedrockBatchCodec::decode($rakNetPayload, CompressionMode::Uncompressed, $limits);
```

After NetworkSettings negotiates zlib for a RakNet 11 connection, use `CompressionMode::NegotiatedZlib` and pass the exact advertised threshold. Encoding deterministically selects `0xff` plain bytes below the threshold and `0x00` raw DEFLATE at or above it; decoding validates and dispatches either prefix per batch.

For encrypted traffic, compose the layers without exposing marker placement to the caller:

```php
use Bedriox\Protocol\Encryption\BedrockEncryptedEnvelopeCodec;

$wirePayload = BedrockEncryptedEnvelopeCodec::encode($clearBatchEnvelope, $outboundEncryptor);
$clearBatchEnvelope = BedrockEncryptedEnvelopeCodec::decode($wirePayload, $inboundDecryptor);
```

The codec guarantees that the outer `0xfe` marker remains clear. Each connection and direction requires its own encryptor or decryptor.

Bedrock discovery semantics are constructed independently from RakNet transport:

```php
use Bedriox\Protocol\Discovery\AdvertisedGameMode;
use Bedriox\Protocol\Discovery\BedrockServerAdvertisement;

$advertisement = new BedrockServerAdvertisement(
    motd: 'Bedriox',
    onlinePlayers: 3,
    maximumPlayers: 20,
    serverId: 1234,
    subMotd: 'Fast PHP',
    gameMode: AdvertisedGameMode::Survival,
    nintendoLimited: false,
    ipv4Port: 19132,
    ipv6Port: 19133,
);

$opaqueRakNetStatus = $advertisement->encode();
```

The edition, protocol 2193, and game version 1.26.50 fields come from Protocol's current authority rather than caller-provided numbers. The Nintendo-limited field is a boolean API: `true` encodes the verified wire value `0`, while `false` encodes `1`. Counts, ports, UTF-8 text, separators, controls, and the complete 352-byte transport budget are validated before a payload is returned.

`Survival` is deliberately the only advertised game-mode case because it is the qualified Bedriox configuration. Adding another spelling requires an independently sourced exact vector and retail discovery qualification; ordinary gameplay game-mode enums do not establish server-list compatibility.

Packet 30 can be decoded at the protocol boundary without applying its actions:

```php
use Bedriox\Protocol\Packet\BedrockPacketCodec;
use Bedriox\Protocol\Packet\InventoryTransactionPacket;
use Bedriox\Protocol\Packet\PacketIds;

$packet = BedrockPacketCodec::decode(PacketIds::INVENTORY_TRANSACTION, $payload);
if (!$packet instanceof InventoryTransactionPacket) {
    throw new LogicException('Unexpected packet registry result.');
}

// Pass the immutable typed value to server-owned validation and simulation policy.
```

The codec validates the complete current layout and exact payload termination. Successful decoding grants no inventory, entity, block-use, or gameplay authority.

Authoritative inventory snapshots and one-slot corrections use the same typed descriptor:

```php
use Bedriox\Protocol\Packet\InventoryContentPacket;
use Bedriox\Protocol\Packet\InventoryItemStack;
use Bedriox\Protocol\Packet\InventorySlotPacket;

$grass = new InventoryItemStack(2, 64, 0, 1, $grassBlockRuntimeId, '');
$slots = array_fill(0, 36, InventoryContentPacket::emptySlot());
$slots[0] = $grass;
$initial = new InventoryContentPacket(0, $slots);
$correction = new InventorySlotPacket(0, 0, $grass);
```

Runtime item and block IDs must already be valid for the current protocol. The server remains responsible for stack ownership, mutation, and reconciliation policy.
