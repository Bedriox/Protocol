# Security model

All network bytes are hostile. Codecs must enforce caller-supplied or protocol-defined size, depth, count, decompression, and allocation limits before allocating or iterating. Truncated, overlong, non-canonical where required, and state-invalid input must fail predictably.

Readers accept an explicit maximum input size. Writers accept an explicit total capacity. String limits are byte limits, are checked before content reads, and are followed by UTF-8 validation. Callers remain responsible for selecting context-appropriate limits; using the largest transport frame as every field limit is unsafe.

Cryptographic primitives must come from maintained platform libraries. Do not invent cryptography or log secrets, tokens, raw identity chains, or session keys.

Verified client appearance data remains size-, signature-, and shape-bounded. `SkinGeometryData` accepts either a JSON object or the exact JSON `null` value used when retail clients have no custom geometry; every other scalar or array shape is rejected.

Bounded JOSE, P-384, ECDH, and login-handshake building blocks are documented in [`security-primitives.md`](security-primitives.md). They intentionally provide no identity or trust policy and perform no network key retrieval.

Batch decompression retains independent compressed-input, decompressed-output, expansion-ratio, packet-count, and per-packet limits. Compression algorithm mismatches, truncated streams, bytes after the stream terminator, zero-length entries, non-canonical lengths, reserved packet-header bits, and invalid game-packet markers fail before packet dispatch.

Report vulnerabilities through GitHub private vulnerability reporting as described in [`SECURITY.md`](../SECURITY.md).
