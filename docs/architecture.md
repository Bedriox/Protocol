# Architecture

## Boundary

This package translates bounded byte sequences for Bedriox's single supported Bedrock release into protocol values and back. It has no socket, event-loop, player, world, command, or persistence responsibility.

The intended dependency direction is `Bedriox server -> Bedriox Protocol -> immutable Bedriox Data artifacts`. Protocol must never import the server or RakNet packages. Any data dependency must be explicit, versioned, and read-only.

## Implemented layers

1. `Codec`: implemented immutable bounded byte readers/writers; fixed-width little-endian scalars; canonical VarInt/VarLong and ZigZag codecs; bounded UTF-8.
2. Packet-scoped NBT: bounded retained NBT values and the required little-endian-to-network conversion live beside the packets that own them; there is no general-purpose NBT namespace.
3. `Discovery`: the immutable typed MCBE server advertisement and its bounded semicolon payload. UDP, ping/pong framing, server identity generation, and socket policy remain outside this package.
4. `Packet`: generic headers, immutable framed-payload values, and the sole supported Bedrock packet set in an unversioned namespace.
5. `Version`: one centralized protocol and game-version declaration used by admission and discovery.
6. `Batch` and `Encryption`: implemented bounded packet batching, threshold-aware negotiated zlib framing, and stateful AES-256-CTR envelopes with a clear `0xfe` marker.
7. `Security`: bounded encoding and OpenSSL-backed ES384/P-384 handshake primitives; authentication and trust policy remain in the server layer.

Decoding APIs must fail closed on truncation, overflow, invalid state, or configured limits. Packet classes cannot mutate server state. The consumer validates session state before dispatch.

## Codec state model

`ByteBufferReader` and `ByteBufferWriter` are immutable. Every successful read returns a `ReadResult` containing the value and a new advanced reader. Every successful write returns a new writer. Failed operations leave the original object usable and unchanged.

PHP cannot represent every unsigned 64-bit value as an integer. `UnsignedLong` therefore stores high and low unsigned 32-bit limbs. Signed VarLong uses PHP's full signed 64-bit integer range and Bedrock ZigZag encoding.

The codec exception hierarchy is stable and deterministic:

- `InvalidValueException`: invalid caller value, range, offset, or limit.
- `BufferUnderflowException`: input ends before a complete value.
- `BufferOverflowException`: configured buffer capacity is exceeded.
- `MalformedDataException`: structurally invalid, overlong, overflowing, non-canonical, or invalid UTF-8 wire data.

## Version policy

Protocol identifiers are explicit values, never inferred from client text. A Minecraft upgrade replaces the sole packet set, centralized compatibility declaration, and golden fixtures together. Parallel version namespaces and compatibility aliases are intentionally unsupported.
