# Security primitives

`Bedriox\Protocol\Security` provides bounded, policy-free primitives for the Bedrock login handshake. It does not decide whether a presented key or identity is trusted and it never fetches keys or certificates from the network.

## Contract

- `Base64Url` accepts canonical, unpadded RFC 7515 base64url only. Standard padded base64 is used separately for the Bedrock handshake `x5u` SPKI and `salt` values.
- `BoundedJson` accepts a JSON object within explicit byte/depth limits and rejects duplicate object-member names.
- `CompactJws` accepts exactly three non-empty compact segments, protected `alg: ES384`, ordinary base64url payloads, and a 96-byte JOSE signature. Critical or detached/unencoded payload extensions are rejected by member presence, including a JSON `null` value.
- `JoseSignature` strictly converts the JOSE `R || S` form to and from canonical ASN.1 DER ECDSA signatures. Both scalars must be in the P-384 interval `1..n-1`; negative, redundant, long-form, truncated, zero, out-of-range, oversized, and trailing encodings are rejected symmetrically.
- `P384` imports only canonical standard-base64 DER SubjectPublicKeyInfo values which OpenSSL identifies as the P-384 curve. Signing uses SHA-384; ECDH must produce a 48-byte shared secret.
- `P384::deriveSessionKey()` returns the raw 32-byte `SHA-256(salt || sharedSecret)` digest and requires a 16-byte salt and 48-byte P-384 secret.
- `HandshakeJwt` emits a protected header containing `alg: ES384` and `x5u` containing standard-base64 DER SPKI, plus a payload containing the standard-base64 16-byte salt.

The default `SecurityLimits` are conservative allocation ceilings, not trust decisions. Consumers should lower them where their enclosing packet has tighter negotiated bounds. Parsed tokens, identity claims, public keys, and signatures must not be logged. Shared secrets, salts combined with secrets, private keys, and derived keys must be released promptly by the session owner; PHP strings cannot guarantee secure zeroization.

## Key generation and platform behavior

`OpenSslEphemeralKeyFactory` requests `secp384r1` directly from `ext-openssl`. There is deliberately no software or weaker-curve fallback. An application can inject another `EphemeralKeyFactory` implementation only if it returns a validated `P384KeyPair` backed by OpenSSL keys.

`P384KeyPair` also compares the public coordinates reported for both handles. Two individually valid P-384 keys cannot be combined into a mismatched pair.

Some Windows PHP distributions have an absent or invalid default OpenSSL configuration. Applications may explicitly inject a readable OpenSSL configuration path into `OpenSslEphemeralKeyFactory`; generation still fails closed with `CryptographicException` when OpenSSL cannot satisfy P-384. There is no process-global environment mutation and no cryptographic fallback.

The test suite supplies its own minimal provider/request configuration at `tests/Fixtures/openssl.cnf`. It contains no key material or secrets, is passed directly to each generation call, and makes the same P-384 sign/verify/ECDH tests mandatory on supported Windows and Linux jobs. PHPUnit treats skipped, incomplete, and risky tests as gate failures.

## Explicit exclusions

The separate identity-proof layer can validate a legacy three-token chain against an explicitly injected pinned root, or a one-token self-signed chain only when the caller explicitly selects that mode. This package does not select authentication policy, bundle or update a Mojang trust root, manage replay state, fetch JWKS/x5u resources, or retain session secrets. Those decisions belong to higher layers. Bedrock encryption framing uses AES-256-CTR after negotiation and must not assume CFB8.
