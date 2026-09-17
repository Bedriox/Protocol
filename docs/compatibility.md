# Compatibility

| Component | Current policy |
|---|---|
| PHP | 64-bit PHP `^8.4` (8.4 through 8.x) |
| Operating systems | Platform-neutral library; CI covers current Ubuntu and Windows runners |
| Required extensions | `ext-zlib` for compression and `ext-openssl` with P-384 support for login security primitives |
| Bedrock protocol | Protocol 2193 / Minecraft 1.26.50 wire authority; Minecraft 1.26.51 qualified retail client |
| Package stability | Pre-1.0; minor releases may break APIs with changelog and migration notes |

Protocol 2193 is the sole accepted target. Minecraft 1.26.50 supplies the pinned official schema; 1.26.51 is accepted only because retail qualification confirms the same protocol. Packet and encryption APIs use unversioned Bedrock names; an explicit protocol argument is retained at selected boundaries for source compatibility and validation, but every value other than 2193 is rejected. PlayerAuthInput uses the compact current framing.

Packet-specific support is documented in `bedrock-packets.md`. StartGame and a dataset-backed fixed-flat chunk profile are available; this does not claim complete gameplay interoperability.

Security operations require an OpenSSL build capable of `secp384r1`, SHA-384 ECDSA, and ECDH. There is no cryptographic fallback; a broken local OpenSSL configuration is an environment error.

The repository will publish an exact protocol-to-package-version table before its first usable release. "Latest Bedrock" is never a compatibility guarantee.
