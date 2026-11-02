# Codebase map

Bedriox/Protocol owns bounded Bedrock wire representation. It never owns NetherNet delivery, login policy, a session lifecycle, or mutable game state.

| Area | Ownership |
|---|---|
| `src/Codec/` and `src/Value/` | Immutable byte readers/writers and canonical numeric/string values. |
| `src/Batch/` | Bedrock packet headers, batches, compression framing, and negotiated limits. |
| `src/Encryption/` | Stateful encrypted batch and integrity framing, not authentication policy. |
| `src/Discovery/` | Typed protocol-2225 MCBE advertisement semantics and bounded text encoding, not NetherNet signaling or data channels. |
| `src/Identity/` and `src/Security/` | Validated proof values and cryptographic primitives consumed by server policy. |
| `src/Packet/` | Immutable packet values, typed codecs, packet-scoped NBT handling, packet registry, initialization data, and terrain serialization. |
| `src/ProtocolVersion.php` | Sole accepted protocol and game-version authority. |
| `tests/` | Independent vectors, boundaries, malformed input, interoperability contracts, and regression evidence. |

Put a Bedrock field, advertisement value, or packet codec here. Put signaling and data-channel transport in Bedriox/NetherNet, admitted registries in Bedriox/Data, and phase decisions or gameplay effects in Bedriox. Advertisement encoding communicates advertised data only; packet decoding communicates received data only. Neither grants authority.

Public packet names stay unversioned. An upgrade replaces the one current wire authority and its vectors rather than adding protocol-number namespaces.
