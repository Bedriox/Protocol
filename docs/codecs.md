# Codec contract

## Immutable buffers

Create readers with `ByteBufferReader::fromString($bytes, $maximumBytes)` and writers with `ByteBufferWriter::withCapacity($maximumBytes)`. Neither type mutates. A read returns `ReadResult<T>`; use its `value` and advanced `reader`. A write returns the advanced writer. Ignoring the returned object intentionally ignores the operation.

Limits are mandatory. Input larger than the reader limit is rejected immediately. A writer rejects an append before changing state if its total capacity would be exceeded.

## Numerical primitives

The buffer API provides unsigned byte, signed/unsigned 16-bit and 32-bit little-endian integers, signed/unsigned 64-bit little-endian values, IEEE-754 little-endian float/double, unsigned VarInt/VarLong, and ZigZag signed VarInt/VarLong.

Unsigned 32-bit values fit in PHP's 64-bit integer. Unsigned 64-bit values use `UnsignedLong(high, low)`, where each limb is in `0..4294967295`. `UnsignedLong::toSignedBits()` preserves the bit pattern; it is not a checked numerical conversion. Use `compareTo()` for ordering unsigned ticks or counters across PHP's signed boundary.

Variable integers must use the shortest canonical representation. Decoders reject values wider than 32 or 64 bits, too many continuation bytes, non-canonical encodings, invalid offsets, and truncation.

## Strings

`writeString()` and `readString()` use an unsigned-VarInt byte-length prefix. The explicit maximum is measured in bytes, not Unicode code points. Values must be valid UTF-8. The decoder validates the declared bound before reading the contents.

## Exceptions

All expected codec failures derive from `CodecException`. Applications may catch a specific subtype to distinguish local programming/configuration errors from hostile or incomplete wire data. Exception messages are diagnostic; subtype behavior is the compatibility contract.

## Bedrock packet and batch framing

`PacketHeader` packs a 10-bit packet ID, 2-bit sender subclient ID, and 2-bit target subclient ID into one canonical unsigned VarInt. Decoding rejects non-canonical VarInts and any reserved bit above bit 13. `PacketFrame` retains the still-uninterpreted packet payload; this layer does not assign packet IDs or implement login policy.

Current `PlayerAuthInput` block actions are target-bearing except `StopDestroyBlock`. Stop is represented explicitly without a position or face so the following conditional fields remain aligned; constructors reject every mismatched action/target shape.

`PacketBatchCodec` represents an uncompressed batch as one or more packet frames, each preceded by its canonical unsigned-VarInt byte length. `BatchLimits` independently caps network input, decompressed bytes, compression ratio, packet count, and individual packet bytes before slicing or retaining data.

`BedrockBatchCodec` adds the `0xfe` game-packet marker and delegates compression framing. Its explicit modes are `Uncompressed` before NetworkSettings, negotiated `None` (`0xff`), threshold-aware `NegotiatedZlib`, and legacy unprefixed RFC 1950 `Zlib`. Negotiated zlib emits `0xff` with plain bytes below the configured threshold and `0x00` with raw DEFLATE at or above it. Threshold zero therefore compresses every non-empty batch; disabling compression requires negotiating `None`. Decoding dispatches the prefix per batch, accepts only the representation permitted by the threshold, and rejects Snappy or unknown prefixes.

`BedrockEncryptedEnvelopeCodec` is the safe composition boundary for encrypted traffic. It removes the clear `0xfe` marker before the compressed batch and integrity trailer enter the continuous cipher, then restores the marker outside the ciphertext. The reverse path validates the marker before consuming decryptor state.

## Chunk palette storage

`PalettedStorage` accepts an expanded runtime-ID palette and one validated palette index per X-Z-Y cell. `PackedPalettedStorage` accepts the equivalent canonical little-endian Bedrock word array when a caller already owns a packed immutable snapshot. Both forms share the same section and biome boundaries and produce the same storage bytes; the serializer writes validated packed words directly instead of expanding and repacking every cell.

Block network identities are canonical signed 32-bit hashes in memory. Chunk and subchunk palettes encode them as signed VarInts. `BlockNetworkId` exposes the corresponding unsigned 32-bit bit pattern for packet fields whose schema uses an unsigned VarInt; callers must choose the representation at the packet boundary instead of passing ambiguous unsigned integers through the domain model.

Packed inputs declare their exact entry count and admitted bit width. Construction rejects incorrect word lengths, palette indexes outside the supplied palette, non-zero trailing entries, and non-canonical padding. Zero-bit storage is limited to a singleton palette with no words.

Decompression uses bounded incremental input, rejects incomplete streams and bytes after the stream terminator, and checks both absolute output size and expansion ratio before retaining further output.
Player lifecycle support includes bounded actor hurt, death, and respawn events,
clientbound death information, and the three-state bidirectional respawn
conversation. Actor events use the current optional fire-position field;
unsupported event types and malformed optional values fail closed.

Protocol-2193 player display support covers all twelve current text variants,
all nine title operations, and toast notifications. Text payload variants are
validated against their text types before fields are retained. Title timing is
encoded as signed VarInts, and current XUID, platform identity, and filtered
text fields remain explicit bounded strings.

Authoritative item-use support exposes the current typed use, release, hand,
prediction, and cooldown values without applying gameplay state. Armor uses the
five-descriptor packet-32 snapshot, while main-hand and offhand equipment use
packet 31 with named inventory window IDs. Hunger and saturation updates use
bounded player-attribute factories; exhaustion deliberately remains outside the
wire projection.

Crafting recipe snapshots use immutable shaped, shapeless, and multi recipe
values. The codec bounds registry, grid, ingredient, result, string, NBT, and
unlock-requirement sizes before iteration or allocation. It preserves current
recipe network IDs and all current crafting request action bodies, but treats
every decoded value as intent for an authoritative consumer to validate.

Weather presentation uses the typed rain and thunder level-event transitions.
Start transitions require an unsigned 16-bit non-zero intensity; stop
transitions carry zero. Weather state, duration, cycling, and policy remain
owned by the server rather than the protocol package.
