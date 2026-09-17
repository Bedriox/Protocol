# Legacy identity proof verification

`Bedriox\Protocol\Identity` verifies the bounded legacy Bedrock certificate-chain and client-data proofs used during login. It performs cryptographic and structural verification only. Authentication-mode selection, account policy, bans, authorization, replay tracking, and session state remain server responsibilities.

## Certificate chains

`LegacyCertificateChainVerifier` accepts duplicate-free JSON with explicit byte, depth, and lexical-token limits. The `chain` member must contain exactly three compact JWS values in `OnlineLegacy` mode or exactly one in `SelfSignedExplicit` mode. Self-signed acceptance therefore requires an explicit caller choice; an online verification failure is never retried under the insecure mode.

Construct online verification with the existing root-taking API, `new LegacyCertificateChainVerifier($pinnedRoot, $limits)`. Explicit offline verification does not need online trust material: construct it with `LegacyCertificateChainVerifier::forExplicitSelfSigned($limits)`, then pass `CertificateChainMode::SelfSignedExplicit` to `verify()`. A rootless verifier rejects `OnlineLegacy` deterministically before parsing certificate input; it never invents a root, fetches one, or falls back to self-signed verification. Passing `null` directly to the constructor has the same rootless behavior, but the named factory communicates the intended trust mode more clearly.

Each compact token must use ES384. Its protected `x5u` is interpreted exclusively as canonical standard-base64 DER SubjectPublicKeyInfo; it is never treated as a URL and is never fetched. OpenSSL must identify both presented and linked keys as P-384. Each token verifies with its protected key, and its `identityPublicKey` must equal the next token's protected key. Online mode fixes the configured root key at certificate index 1, matching the supported legacy chain contract; callers cannot move the trust anchor to another position.

Integer `nbf` and `exp` claims are enforced when present. Explicit `null` or any other non-integer representation is malformed. `nbf` may not be in the future, while `exp` must be strictly later than the injected current epoch second. The final token must contain a linked identity public key plus non-empty `extraData.displayName`, canonical UUID `extraData.identity`, and decimal `extraData.XUID` within configured byte limits.

The result is an immutable `VerifiedIdentity` containing only those safe identity fields, the verified final public key, and the explicit verification mode. Online legacy mode requires a non-empty decimal XUID. Explicit self-signed mode permits the protocol's empty XUID representation but still rejects any non-empty non-decimal value. It does not expose chain tokens or arbitrary claims.

## Client data

`ClientDataJwtVerifier` parses a bounded compact ES384 JWS and verifies its signature with the final identity public key before inspecting any claims. Client-data verification is bound directly to that previously verified key; client-data `x5u` is neither required nor used for key selection. This follows the pinned verification behavior and avoids treating a redundant client-controlled header as authority.

The verifier decodes canonical standard-base64 skin, cape, geometry, and animated-image fields. Image dimensions are positive and bounded, except that both cape dimensions may be zero for an empty cape. Every image must contain exactly `width * height * 4` decoded RGBA bytes. Geometry must decode to bounded duplicate-free JSON with its own lexical-token ceiling. Animation count, expression metadata, individual decoded sizes, JSON depth/token count, and aggregate decoded bytes are bounded before an immutable `VerifiedClientData` is returned. When present, the signed skin ID, PlayFab ID, resource-patch JSON, geometry engine version, animation JSON, cape/full-skin IDs, arm/color values, and appearance flags are retained in bounded decoded form so a later player-list codec never needs the raw JWT.

Default client-data JWS limits fit the packet layer's 1 MiB login-JWT ceiling: at most 780,000 decoded JSON bytes within a 1 MiB compact JWS. Default decoded appearance limits are 256 KiB skin, 64 KiB cape, 64 KiB geometry, and 512 KiB aggregate. Configuration rejects any aggregate whose minimum base64 representation cannot fit its JWS payload ceiling.

These values can still contain user-selected appearance data. Do not log raw JWS values, decoded images, geometry, certificate chains, or public-key-bearing authentication payloads. Verification does not make client-provided cosmetics suitable for persistence or redistribution.
