# Repository Instructions

## Purpose and boundaries

This repository provides Minecraft: Bedrock Edition wire primitives and packet codecs for one explicitly bounded compatible release family. It may own bounded binary/NBT codecs, immutable packet values, the current packet registry, batching, compression framing, and encryption framing.

It does not own sockets, RakNet reliability, authentication policy, sessions, players, worlds, commands, persistence, or server behavior. Dependency direction is:

```text
Bedriox server -> Bedriox/Protocol -> immutable, versioned Bedriox/Data artifacts
```

Protocol must never depend on Bedriox or Bedriox/RakNet. A future Bedriox/Data dependency must be explicit, versioned, and read-only.

## Repository layout

- `src/Codec/`: immutable bounded readers/writers and numerical/string codecs.
- `src/Exception/`: deterministic codec failure categories.
- `src/Value/`: protocol-safe value objects such as unsigned 64-bit limbs.
- `src/ProtocolVersion.php`: validated protocol-version identity.
- `src/Batch/`, `src/Encryption/`, `src/Identity/`, `src/Packet/`, and `src/Security/`: implemented bounded Bedrock framing, values, packet-scoped NBT handling, and codecs.
- `tests/`: boundary, vector, round-trip, malformed-input, integration, and immutability tests mirroring production areas.
- `tests/PlatformTest.php`: required runtime/platform assertions.
- `docs/`: codec contract, architecture, development, usage, testing, security, compatibility, and troubleshooting documentation.

Keep NBT, packet, batch, identity, encryption, and security code in their distinct namespaces rather than mixing them into primitive codecs. Keep the current packet surface unversioned and centralize its compatibility declaration in `ProtocolVersion`. Do not commit `vendor/`, generated game data, credentials, personal packet captures, or unpublished proprietary material.

## Change safety and preservation

- Name the wire contract, packet IDs, public APIs, and consumer journeys a change is intended to affect before editing. Do not refactor an unrelated working path as part of feature work.
- Capture the relevant baseline test result first. Every reported disconnect, malformed decode, compatibility regression, or changed golden byte sequence requires a focused regression test that fails for the original defect.
- Preserve all unaffected golden vectors byte-for-byte. A vector may change only with identified wire authority, a documented compatibility reason, and coordinated consumer tests.
- Never relax parsing globally to make one client packet pass. Add the narrow typed schema, limits, phase-neutral value, and adversarial coverage required by the observed contract.
- Packet support is conversationally closed: before adding or changing an outbound packet, enumerate every normal current-client reply, acknowledgement, update, and teardown it may trigger. Add bounded typed codecs, registry entries, malformed-input coverage, and consumer journey tests for that reachable packet family; do not declare a one-way encoder complete while a normal reply remains unregistered.
- A registry, packet ID, protocol declaration, StartGame field, terrain layout, encryption envelope, or public value-object change is cross-repository work. Run the owning gate, affected Bedriox consumer tests, and the workspace verifier before declaring it complete.
- Compare third-party implementations only for independently documented interoperability facts. Do not copy or translate their code, structure, fixtures, or registries.
- Before handoff, inspect the final diff and prove every changed file is necessary for the stated objective. If an existing behavior cannot be preserved, stop and obtain an accepted compatibility decision rather than silently changing it.

Follow [`docs/change-safety.md`](docs/change-safety.md) for the required baseline, implementation, verification, and rollback record.

## Coding rules

- Target 64-bit PHP 8.4 through PHP 8.x; all PHP source uses `declare(strict_types=1);` and the `Bedriox\Protocol\` namespace.
- Treat every byte as hostile. Validate size, range, count, depth, offset, canonical representation, and allocation bounds before reading or allocating.
- Preserve immutable reader/writer behavior: successful operations return advanced values; failures leave the original object unchanged.
- Use `UnsignedLong` for the full unsigned 64-bit domain. Do not use floats or lossy casts for integer wire values.
- Distinguish caller errors, truncation, capacity exhaustion, and malformed wire data with the existing exception hierarchy.
- Packet values must not mutate server state. The protocol version and packet registry must be explicit; never infer compatibility from a client version string.
- Keep packet names unversioned. The repository has one current Bedrock target; an upgrade replaces its wire authority and golden vectors rather than adding parallel version namespaces.
- Keep terrain delivery request-driven for the current Bedrock family: LevelChunk carries the bounded biome shell and SubChunk responses carry section data. Bound request counts, coordinates, response bytes, and palette sizes before allocation.
- Maintain exactly one supported Bedrock target. The wire authority is Minecraft 1.26.50 / protocol 2193; Minecraft 1.26.51 is a qualified same-protocol retail client. Upgrades replace this authority and its golden vectors rather than adding parallel version branches.
- Prove generated terrain semantically: tests must decode every cell, palette entry, biome copy-last marker, and both height maps. Packet length checks alone are insufficient.
- Unsupported inventory actions may be rejected by request ID but must never mutate state or be registered as unbounded opaque no-ops.
- Use platform-independent pack/unpack formats and add known byte vectors, not only encode/decode round trips.

## Quality commands

```shell
composer install
composer check
composer validate --strict
composer audit --locked
```

`composer check` runs PHPUnit and maximum-level PHPStan. Add minimum/maximum boundaries, known wire vectors, offsets, every meaningful truncation point, overflow, non-canonical input, immutable-state, and round-trip coverage for every new primitive. Parsers and decompressors also require adversarial size/depth tests and fuzz or property tests where practical.

If a change affects the server contract, also run the Bedriox workspace verifier from the sibling `Bedriox` checkout and the consumer tests that exercise the changed API.

## Documentation, licensing, and security

Update `docs/codecs.md` or the relevant repository-local guide plus `CHANGELOG.md` whenever public behavior changes. Update `docs/compatibility.md` for PHP, platform, package, or Bedrock-version changes. Architectural or versioning decisions requiring alternatives or community review need a written design proposal approved by the maintainers.

Maintain a clean-room implementation. Do not copy or translate PocketMine, RakLib, Nukkit, Cloudburst, Dragonfly, decompiled, leaked, GPL-incompatible, or proprietary implementation code. Record legally required third-party copyright and license attribution in `NOTICE` or `THIRD_PARTY_NOTICES.md` before admitting external material.

Enforce explicit limits before slicing, iterating, decompressing, or allocating. Use maintained platform cryptography only, and never log tokens, identity chains, keys, credentials, or raw sensitive payloads. Report suspected vulnerabilities through the private process in `SECURITY.md`.

## Cross-repository coordination

- Protocol owns wire representations; Bedriox owns session policy and state; Bedriox/RakNet owns transport; Bedriox/Data owns approved immutable registries and artifacts.
- Do not move domain behavior across these boundaries for convenience.
- Coordinate breaking public API or supported-version changes with Bedriox consumers and compatibility manifests.
- Do not edit sibling repositories unless the task explicitly includes them. Run each affected repository's quality gate after coordinated changes.

## Commits

Keep commits focused and use an imperative, descriptive subject, preferably the established `type: summary` form. Commit messages must not contain personal email addresses or identity trailers. Contributions are accepted under GPL-3.0-only. Do not rewrite or discard unrelated contributor work.

## Definition of done

A change is complete when boundaries and immutable contracts remain intact, success and adversarial paths have deterministic tests, known vectors independently confirm wire behavior, `composer check`, strict Composer validation, and the locked audit pass, documentation/changelog/compatibility/notices are current, consumer coordination is complete where applicable, and the final diff contains only intended, reviewable, legally usable material.
